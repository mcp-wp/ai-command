<?php

namespace WP_CLI\AiCommand\MCP\Servers\WordPress\Tools;

use Psr\Log\LoggerInterface;
use WP_REST_Request;
use WP_REST_Response;

readonly class RestApi {
	public function __construct( private LoggerInterface $logger ) {
	}

	private function args_to_schema( $args = [] ) {
		$schema   = [];
		$required = [];

		if ( empty( $args ) ) {
			return [];
		}

		foreach ( $args as $title => $arg ) {
			$description = $arg['description'] ?? $title;
			$type        = $this->sanitize_type( $arg['type'] ?? 'string' );

			$schema[ $title ] = [
				'type'        => $type,
				'description' => $description,
			];
			if ( isset( $arg['required'] ) && $arg['required'] ) {
				$required[] = $title;
			}
		}

		return [
			'type'       => 'object',
			'properties' => $schema,
			'required'   => $required,
		];
	}

	protected function sanitize_type( $type ) {

		$mapping = array(
			'string'  => 'string',
			'integer' => 'integer',
			'number'  => 'integer',
			'boolean' => 'boolean',
		);

		// Validated types:
		if ( ! \is_array( $type ) && isset( $mapping[ $type ] ) ) {
			return $mapping[ $type ];
		}

		if ( 'array' === $type || 'object' === $type ) {
			return 'string'; // TODO, better solution.
		}
		if ( empty( $type ) || 'null' === $type ) {
			return 'string';
		}

		if ( ! \is_array( $type ) ) {
			throw new \Exception( 'Invalid type: ' . $type );
		}

		// Find valid values in array.
		if ( \in_array( 'string', $type, true ) ) {
			return 'string';
		}
		if ( \in_array( 'integer', $type, true ) ) {
			return 'integer';
		}
		// TODO, better types handling.
		return 'string';
	}

	public function get_tools(): array {
		$server = rest_get_server();
		$routes = $server->get_routes();
		$tools  = [];

		foreach ( $routes as $route => $endpoints ) {
			foreach ( $endpoints as $endpoint ) {
				foreach ( $endpoint['methods'] as $method_name => $enabled ) {
					$information = new RouteInformation(
						$route,
						$method_name,
						$endpoint['callback'],
					);

					if ( ! $information->is_wp_rest_controller() ) {
						continue;
					}

					$tool = [
						'name'        => $information->get_sanitized_route_name(),
						'description' => $this->generate_description( $information ),
						'inputSchema' => $this->args_to_schema( $endpoint['args'] ),
						'callable'    => function ( $inputs ) use ( $route, $method_name, $server ) {
							return json_encode( $this->rest_callable( $inputs, $route, $method_name, $server ) );
						},
					];

					$tools[] = $tool;
				}
			}
		}

		return $tools;
	}

	/**
	 * Create description based on route and method.
	 *
	 *
	 * Get a list of posts             GET /wp/v2/posts
	 * Get post with id                GET /wp/v2/posts/(?P<id>[\d]+)
	 */
	protected function generate_description( RouteInformation $information ): string {

		$verb = match ( $information->get_method() ) {
			'GET' => 'Get',
			'POST' => 'Create',
			'PUT', 'PATCH'  => 'Update',
			'DELETE' => 'Delete',
		};

		$schema = $information->get_wp_rest_controller()->get_public_item_schema();
		$title  = $schema['title'];

		$determiner = $information->is_singular()
			? 'a'
			: 'list of';

		return $verb . ' ' . $determiner . ' ' . $title;
	}

	protected function rest_callable( $inputs, $route, $method_name, \WP_REST_Server $server ): array {
		preg_match_all( '/\(?P<(\w+)>/', $route, $matches );

		foreach ( $matches[1] as $match ) {
			if ( array_key_exists( $match, $inputs ) ) {
				$route = preg_replace( '/(\(\?P<' . $match . '>.*?\))/', $inputs[ $match ], $route, 1 );
			}
		}

		$this->logger->debug( 'Rest Route: ' . $route . ' ' . $method_name );

		if ( false === $inputs['meta'] || null === $inputs['meta'] || '' === $inputs['meta'] || [] === $inputs['meta'] ) {
			unset( $inputs['meta'] );
		}

		foreach ( $inputs as $key => $value ) {
			$this->logger->debug( '  param->' . $key . ' : ' . $value );
		}

		$request = new WP_REST_Request( $method_name, $route );
		$request->set_body_params( $inputs );

		/**
		 * @var WP_REST_Response $response
		 */
		$response = $server->dispatch( $request );

		$data = $server->response_to_data( $response, false );

		// Quick fix to reduce amount of data that is returned.
		// TODO: Improve
		unset( $data['_links'], $data[0]['_links'] );

		if ( isset( $data[0]['slug'] ) ) {
			$debug_data = 'Result List: ';
			foreach ( $data as $item ) {
				$debug_data .= $item['id'] . '=>' . $item['slug'] . ', ';
			}
		} elseif ( isset( $data['slug'] ) ) {
			$debug_data = 'Result: ' . $data['id'] . ' ' . $data['slug'];
		} else {
			$debug_data = 'Unknown format';
		}

		$this->logger->debug( $debug_data );

		return $data;
	}
}
