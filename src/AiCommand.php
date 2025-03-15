<?php

namespace WP_CLI\AiCommand;

use WP_CLI;
use WP_CLI_Command;
use WP_Community_Events;
use WP_Error;

/**
 *
 * Resources: File-like data that can be read by clients (like API responses or file contents)
 * Tools: Functions that can be called by the LLM (with user approval)
 * Prompts: Pre-written templates that help users accomplish specific tasks
 *
 * MCP follows a client-server architecture where:
 *
 * Hosts are LLM applications (like Claude Desktop or IDEs) that initiate connections
 * Clients maintain 1:1 connections with servers, inside the host application
 * Servers provide context, tools, and prompts to clients
 */
class AiCommand extends WP_CLI_Command {
	/**
	 * Greets the world.
	 *
	 * ## OPTIONS
	 *
	 *  <prompt>
	 *  : AI prompt.
	 *
	 * ## EXAMPLES
	 *
	 *     # Greet the world.
	 *     $ wp ai "What are the titles of my last three posts?"
	 *     Success: Hello World!
	 *
	 *     # Greet the world.
	 *     $ wp ai "create 10 test posts about swiss recipes and include generated featured images"
	 *     Success: Hello World!
	 *
	 * @when after_wp_load
	 *
	 * @param array $args Indexed array of positional arguments.
	 * @param array $assoc_args Associative array of associative arguments.
	 */
	public function __invoke( $args, $assoc_args ) {
		$server = new MCP\Server();
		$client = new MCP\Client($server);

		$this->register_tools($server, $client);

		$this->register_resources($server);

		$result = $client->call_ai_service_with_prompt( $args[0] );

		WP_CLI::success( $result );
	}

	// Register tools for AI processing
	private function register_tools($server, $client) {
		$server->register_tool(
			[
				'name'        => 'calculate_total',
				'description' => 'Calculates the total price.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'price'    => [
							'type'        => 'integer',
							'description' => 'The price of the item.',
						],
						'quantity' => [
							'type'        => 'integer',
							'description' => 'The quantity of items.',
						],
					],
					'required'   => [ 'price', 'quantity' ],
				],
				'callable'    => function ( $params ) {
					$price    = $params['price'] ?? 0;
					$quantity = $params['quantity'] ?? 1;

					return $price * $quantity;
				},
			]
		);

		$server->register_tool(
			[
				'name'        => 'greet',
				'description' => 'Greets the user.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'name' => [
							'type'        => 'string',
							'description' => 'The name of the user.',
						],
					],
					'required'   => [ 'name' ],
				],
				'callable'    => function ( $params ) {
					return 'Hello, ' . $params['name'] . '!';
				},
			]
		);

		$server->register_tool(
			[
				'name'        => 'generate_image',
				'description' => 'Generates an image.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'prompt' => [
							'type'        => 'string',
							'description' => 'The prompt for generating the image.',
						],
					],
					'required'   => [ 'prompt' ],
				],
				'callable'    => function ( $params ) use ( $client ) {
					return $client->get_image_from_ai_service( $params['prompt'] );
				},
			]
		);

		$server->register_tool(
			[
					'name'        => 'fetch_wp_community_events',
					'description' => 'Fetches upcoming WordPress community events near a specified city or the user\'s current location. If no events are found in the exact location, nearby events within a specific radius will be considered.',
					'inputSchema' => [
							'type'       => 'object',
							'properties' => [
									'location' => [
											'type'        => 'string',
											'description' => 'City name or "near me" for auto-detected location. If no events are found in the exact location, the tool will also consider nearby events within a specified radius (default: 100 km).',
									],
							],
							'required'   => [ 'location' ],  // We only require the location
					],
					'callable'    => function ( $params ) {
							// Default user ID is 0
							$user_id = 0;

							// Get the location from the parameters (already supplied in the prompt)
							$location_input = strtolower( trim( $params['location'] ) );

							// Manually include the WP_Community_Events class if it's not loaded
							if ( ! class_exists( 'WP_Community_Events' ) ) {
									require_once ABSPATH . 'wp-admin/includes/class-wp-community-events.php';
							}

							// Determine location for the WP_Community_Events class
							$location = null;
							if ( $location_input !== 'near me' ) {
									// Provide city name (WP will resolve coordinates)
									$location = [
											'latitude'  => null, // WP will attempt geolocation
											'longitude' => null,
											'city'      => $location_input,
											'country'   => '', // Optional, WP may infer
									];
							}

							// Instantiate WP_Community_Events with user ID (0) and optional location
							$events_instance = new WP_Community_Events( $user_id, $location );

							// Get events from WP_Community_Events
							$events = $events_instance->get_events();

							// Check for WP_Error
							if ( is_wp_error( $events ) ) {
									return [ 'error' => $events->get_error_message() ];
							}

						// If no events found
						if ( empty( $events['events'] ) ) {
							return [ 'message' => 'No events found near ' . ( $location_input === 'near me' ? 'your location' : $location_input ) ];
						}

						// Format and return the events correctly
						$formatted_events = array_map( function ( $event ) {
							// Log event details to ensure properties are accessible
							error_log( 'Event details: ' . print_r( $event, true ) );

							// Initialize a formatted event string
							$formatted_event = '';

							// Format event title
							if ( isset( $event['title'] ) ) {
									$formatted_event .= $event['title'] . "\n";
							}

							// Format the date nicely
							$formatted_event .= '  - Date: ' . ( isset( $event['date'] ) ? date( 'F j, Y g:i A', strtotime( $event['date'] ) ) : 'No date available' ) . "\n";

							// Format the location
							if ( isset( $event['location']['location'] ) ) {
									$formatted_event .= '  - Location: ' . $event['location']['location'] . "\n";
							}

							// Format the event URL
							$formatted_event .= isset( $event['url'] ) ? '  - URL: ' . $event['url'] . "\n" : '';

							return $formatted_event;
						}, $events['events'] );

						// Combine the formatted events into a single string
						$formatted_events_output = implode("\n", $formatted_events);

						// Return the formatted events string
						return [
							'message' => "OK. I found " . count($formatted_events) . " WordPress events near " . ( $location_input === 'near me' ? 'your location' : $location_input ) . ":\n\n" . $formatted_events_output
						];
					},
			]
		);

	}

	// Register resources for AI access
	private function register_resources($server) {
		// Register Users resource
		$server->register_resource([
				'name'        => 'users',
				'uri'         => 'data://users',
				'description' => 'List of users',
				'mimeType'    => 'application/json',
				'dataKey'     => 'users', // Data will be fetched from 'users'
		]);

		// Register Product Catalog resource
		$server->register_resource([
				'name'        => 'product_catalog',
				'uri'         => 'file://./products.json',
				'description' => 'Product catalog',
				'mimeType'    => 'application/json',
				'filePath'    => './products.json', // Data will be fetched from products.json
		]);
	}
}
