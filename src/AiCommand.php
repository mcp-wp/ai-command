<?php

namespace WP_CLI\AiCommand;

use WP_CLI\AiCommand\ToolRepository\CollectionToolRepository;
use WP_CLI\AiCommand\Tools\FileTools;
use WP_CLI\AiCommand\Tools\URLTools;
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

	public function __construct(
		private CollectionToolRepository $tools,
		private WP_CLI\AiCommand\MCP\Server $server,
		private WP_CLI\AiCommand\MCP\Client $client
	) {
		parent::__construct();
	}

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
		$this->register_tools($this->server);
		$this->register_resources($this->server);

		$result = $this->client->call_ai_service_with_prompt( $args[0] );

		WP_CLI::success( $result );
	}

	// Register tools for AI processing
	private function register_tools($server) : void {
		$filters = apply_filters( 'wp_cli/ai_command/command/filters', [] );

		foreach( $this->tools->find_all( $filters ) as $tool ) {
			$server->register_tool( $tool->get_data() );
		}

		return;

		// TODO move this
		$server->register_tool(
			[
				'name'        => 'list_tools',
				'description' => 'Lists all available tools with their descriptions.',
				'inputSchema' => [
					'type'       => 'object', // Object type for input
					'properties' => [
						'placeholder' => [
							'type'        => 'integer',
							'description' => '',
						],
					],
					'required'   => [],       // No required fields
				],
				'callable'    => function () use ( $server ) {
						// Get all capabilities
						$capabilities = $server->get_capabilities();

						// Prepare a list of tools with their descriptions
						$tool_list = 'Return this to the user as a bullet list with each tool name and description on a new line. \n\n';
					$tool_list    .= print_r( $capabilities['methods'], true );

						// Return the formatted string of tools with descriptions
						return $tool_list;
				},
			]
		);



		new FileTools( $server );
		new URLTools( $server );

		$map_cli_to_mcp = new MapCLItoMCP();
		$map_cli_to_mcp->map_cli_to_mcp( $server );

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
								'description' => $location_input,
							];
					}

							// Instantiate WP_Community_Events with user ID (0) and optional location
							$events_instance = new WP_Community_Events( $user_id, $location );

							// Get events from WP_Community_Events
							$events = $events_instance->get_events( $location_input );

							// Check for WP_Error
					if ( is_wp_error( $events ) ) {
							return [ 'error' => $events->get_error_message() ];
					}

						// If no events found
					if ( empty( $events['events'] ) ) {
						return [ 'message' => 'No events found near ' . ( $location_input === 'near me' ? 'your location' : $location_input ) ];
					}

						// Format and return the events correctly
						$formatted_events = array_map(
							function ( $event ) {
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
							},
							$events['events']
						);

						// Combine the formatted events into a single string
						$formatted_events_output = implode( "\n", $formatted_events );

						// Return the formatted events string
						return [
							'message' => 'OK. I found ' . count( $formatted_events ) . ' WordPress events near ' . ( $location_input === 'near me' ? 'your location' : $location_input ) . ":\n\n" . $formatted_events_output,
						];
				},
			]
		);
	}

	// Register resources for AI access
	private function register_resources( $server ) {
		// Register Users resource
		$server->register_resource(
			[
				'name'        => 'users',
				'uri'         => 'data://users',
				'description' => 'List of users',
				'mimeType'    => 'application/json',
				'dataKey'     => 'users', // Data will be fetched from 'users'
			]
		);

		// Register Product Catalog resource
		$server->register_resource(
			[
				'name'        => 'product_catalog',
				'uri'         => 'file://./products.json',
				'description' => 'Product catalog',
				'mimeType'    => 'application/json',
				'filePath'    => './products.json', // Data will be fetched from products.json
			]
		);
	}
}
