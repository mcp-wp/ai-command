<?php

namespace WP_CLI\AiCommand;

class MapRESTtoMCP {

	public function __construct() {
		$this->rest_api_routes = include( 'RESTControllerList.php' );
	}

	public function map_rest_to_mcp() {
        $routes = rest_get_server()->get_routes();
		foreach ( $routes as $route => $endpoints ) {
			foreach ( $endpoints as $endpoint ) {
				// Generate a tool name based on route and method (e.g., "GET_/wp/v2/posts")
				$tool_name = strtolower( str_replace(['/', '(', ')', '?', '[', ']', '+', '\\', '<', '>', ':', '-'], '_', $route ) );
				$tool_name = preg_replace('/_+/', '_', trim($tool_name, '_'));

				$server->register_tool( [
					'name' => $tool_name,
					'description' => $this->get_endpoint_description( $endpoint ),
					'inputSchema' => $this->args_to_schema( $endpoint['args'] ),
					'callable' => function ( $inputs ) use ( $endpoint, $route ){
						$request = new \WP_REST_Request( pick_method( $endpoint['methods'] ), $route );
						$request->set_body_params( $inputs );
						$response = rest_get_server()->dispatch( $request );
						return rest_get_server()->response_to_data( $response );
					},
				] );
			}
		}
	}
}
