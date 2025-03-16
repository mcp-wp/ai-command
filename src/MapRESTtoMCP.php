<?php

namespace WP_CLI\AiCommand;

use WP_CLI\AiCommand\RouteInformation;
use WP_CLI;
use WP_CLI\AiCommand\MCP\Server;
use WP_REST_Request;


class MapRESTtoMCP {

    public function __construct(
        private array $rest_routes
    ) {}

	public function args_to_schema( $args = [] ) {
		$schema   = [];
		$required = [];

		if ( empty( $args ) ) {
			return [];
		}

		foreach ( $args as $title => $arg ) {
			$description = $arg['description'] ?? $title;
			$type 		 = $this->sanitize_type( $arg['type'] ?? 'string' );

			$schema[ $title ] = [
				'type' => $type,
				'description' => $description,
			];
			if ( isset( $arg['required'] ) && $arg['required'] ) {
				$required[] = $title;
			}
		}

		return [
			'type' => 'object',
			'properties' => $schema,
			'required' => $required,
		];
	}

	protected function sanitize_type( $type) {

		$mapping = array(
			'string' => 'string',
			'integer' => 'integer',
			'number' => 'integer',
			'boolean' => 'boolean',
		);

		// Validated types:
		if ( !\is_array($type) && isset($mapping[ $type ]) ) {
			return $mapping[ $type ];
		}

		if ( $type === 'array' || $type === 'object' ) {
			return 'string'; // TODO, better solution.
		}
		if (empty( $type ) || $type === 'null' ) {
			return 'string';
		}

		if ( !\is_array( $type ) ) {
			throw new \Exception( 'Invalid type: ' . $type );
			return 'string';
		}

		// Find valid values in array.
		if ( \in_array( 'string', $type ) ) {
			return 'string';
		}
		if ( \in_array( 'integer', $type )  ) {
			return 'integer';
		}
		// TODO, better types handling.
		return 'string';

	}

	protected function is_route_allowed( $route ) {
		if(! \str_starts_with($route, '/wp/v2')) {
			return false; // Block all non wp/v2 routes for now.
		}

		return ! in_array( $route, $this->rest_routes, true );
	}

	public function map_rest_to_mcp( Server $mcp_server ) {
		$server = rest_get_server();
		$routes = $server->get_routes();

		foreach ( $routes as $route => $endpoints ) {
			foreach ( $endpoints as $endpoint ) {
				if ( ! $this->is_route_allowed($route) ) {
					continue; // This route is the block list.
				}

				foreach( $endpoint['methods'] as $method_name => $enabled ) {
					$information = new RouteInformation(
						$route,
						$method_name,
						$endpoint['callback'],
					);

					if ( ! $information->is_wp_rest_controller() ) {
						continue;
					}

					$tool = [
						'name' => $information->get_sanitized_route_name(),
						'description' => $this->generate_description( $information ),
						'inputSchema' => $this->args_to_schema( $endpoint['args'] ),
						'callable' => function ( $inputs ) use ( $route, $method_name, $server ){
							return $this->rest_callable( $inputs, $route, $method_name, $server );
						},
					];

					$mcp_server->register_tool($tool);
				}

			}
		}

	}

	protected function generate_tool_name($route, $method_name) {
		$singular = '';
		if ( \str_contains( $route, '(?P<' ) ) {
			$singular = 'singular_';
		}
		return sanitize_title($route) . '_' . $singular . strtolower( $method_name );
	}

	/**
	 * Create desciptrion based on route and method.
	 *
	 *
	 * Get a list of posts             GET /wp/v2/posts
	 * Get post with id                GET /wp/v2/posts/(?P<id>[\d]+)
	 */
	protected function generate_description( RouteInformation $information  )  : string {

		$verb = match ($information->get_method()) {
			'GET' => 'Get',
			'POST' => 'Create',
			'PUT', 'PATCH'  => 'Update',
			'DELETE' => 'Delete',
		};

		$schema = $information->get_wp_rest_controller()->get_public_item_schema();
		$title = $schema['title'];

		$determiner = $information->is_singular()
			? 'a'
			: 'list of';

		return $verb . ' ' . $determiner . ' ' . $title;
	}

	protected function rest_callable( $inputs, $route, $method_name, $server ) {
		preg_match_all( '/\(?P<(\w+)>/', $route, $matches );

		foreach( $matches[1] as $match ) {
			if ( array_key_exists( $match, $inputs ) ) {
				$route = preg_replace( '/(\(\?P<'.$match.'>.*?\))/', $inputs[$match], $route, 1 );
			}
		}

		WP_CLI::debug( 'Rest Route: ' . $route . ' ' . $method_name, 'mcp_server' );

		foreach( $inputs as $key => $value ) {
			WP_CLI::debug( '  param->' . $key . ' : ' . $value, 'mcp_server' );
		}

		$request = new WP_REST_Request( $method_name, $route  );
		$request->set_body_params( $inputs );

		/**
		 * @var WP_REST_Response $response
		 */
		$response = $server->dispatch( $request );

		return $server->response_to_data( $response, false );
	}
}
