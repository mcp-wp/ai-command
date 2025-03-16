<?php

namespace WP_CLI\AiCommand;

use WP_CLI\AiCommand\Tools\FileTools;
use WP_CLI\AiCommand\Tools\URLTools;
use WP_CLI;
use WP_CLI_Command;
use WP_CLI\Dispatcher;
use WP_CLI\SynopsisParser;
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
				'name'        => 'list_tools',
				'description' => 'Lists all available tools with their descriptions.',
				'inputSchema' => [
						'type'       => 'object', // Object type for input
						'properties' => [
							'placeholder'    => [
								'type'        => 'integer',
								'description' => '',
							]
						],
						'required'   => [],       // No required fields
				],
				'callable'    => function () use ($server) {
						// Get all capabilities
						$capabilities = $server->get_capabilities();

						// Prepare a list of tools with their descriptions
						$tool_list = 'Return this to the user as a bullet list with each tool name and description on a new line. \n\n';
            $tool_list .= print_r($capabilities['methods'], true);

						// Return the formatted string of tools with descriptions
						return $tool_list;
				},
			]
		);

		$map_rest_to_mcp = new MapRESTtoMCP();
		$map_rest_to_mcp->map_rest_to_mcp( $server );

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
							$events = $events_instance->get_events($location_input);

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

		// Expose WP-CLI commands as tools
		$commands = [
			'cache',
			'config',
			'core',
			'maintenance-mode',
			'profile',
			'rewrite',		
		];

		foreach ( $commands as $command ) {
			$command_to_run = WP_CLI::get_runner()->find_command_to_run( [ $command ] );
			list( $command ) = $command_to_run;

			if ( ! is_object( $command ) ) {
				continue;
			}

			$command_name = $command->get_name();
			
			if ( ! $command->can_have_subcommands() ) {

				$command_desc = $command->get_shortdesc() ?? "Runs WP-CLI command: $command_name";
				$command_synopsis = $command->get_synopsis();
				$synopsis_spec = SynopsisParser::parse( $command_synopsis );

				$properties = [];
				$required = [];

				$properties['dummy'] = [
					'type' => 'string',
					'description' => 'Dummy parameter',
				];

				WP_CLI::debug("Synopsis for command: " . $command_name . " - " . print_r($command_synopsis, true), 'ai');

				foreach ( $command_synopsis as $arg ) {
					if ($arg['type'] === 'positional' || $arg['type'] === 'assoc') {
						$prop_name = str_replace('-', '_', $arg['name']);
						$properties[ $prop_name ] = [
							'type' => 'string',
							'description' => $arg['description'] ?? "Parameter {$arg['name']}"
						];
						
						if (!isset($arg['optional']) || !$arg['optional']) {
							$required[] = $prop_name;
						}
					}
				}

				$server->register_tool([
					'name' => 'wp_' . str_replace(' ', '_', $command_name),
					'description' => $command_desc,
					'inputSchema' => [
						'type' => 'object',
						'properties' => $properties,
						'required' => $required
					],
					'callable' => function($params) use ($command_name) {
						$args = [];
						$assoc_args = [];
						
						// Process positional arguments first
						foreach ($synopsis_spec as $arg) {
							if ($arg['type'] === 'positional') {
								$prop_name = str_replace('-', '_', $arg['name']);
								if (isset($params[$prop_name])) {
									$args[] = $params[$prop_name];
								}
							}
						}
						
						// Process associative arguments and flags
						foreach ($params as $key => $value) {
							// Skip positional args and dummy param
							if ($key === 'dummy') {
								continue;
							}
							
							// Check if this is an associative argument
							foreach ($synopsis_spec as $arg) {
								if (($arg['type'] === 'assoc' || $arg['type'] === 'flag') && 
									str_replace('-', '_', $arg['name']) === $key) {
									$assoc_args[str_replace('_', '-', $key)] = $value;
									break;
								}
							}
						}

						ob_start();
						WP_CLI::run_command(array_merge(explode(' ', $command_name), $args), $assoc_args);
						return ob_get_clean();
					}
				]);
			} else {

				\WP_CLI::debug($command_name . " subcommands: " . print_r($command->get_subcommands(), true), 'ai');
	
				foreach ( $command->get_subcommands() as $subcommand ) {
	
					if ( WP_CLI::get_runner()->is_command_disabled( $subcommand ) ) {
						continue;
					}
	
					$subcommand_name = $subcommand->get_name();
					$subcommand_desc = $subcommand->get_shortdesc() ?? "Runs WP-CLI command: $subcommand_name";
					$subcommand_synopsis = $subcommand->get_synopsis();
					$synopsis_spec = SynopsisParser::parse( $subcommand_synopsis );

					$properties = [];
					$required = [];
		
					$properties['dummy'] = [
						'type' => 'string',
						'description' => 'Dummy parameter',
					];

					foreach ( $synopsis_spec as $arg ) {
						if ($arg['type'] === 'positional' || $arg['type'] === 'assoc') {
							$prop_name = str_replace('-', '_', $arg['name']);
							$properties[ $prop_name ] = [
								'type' => 'string',
								'description' => $arg['description'] ?? "Parameter {$arg['name']}"
							];
							
						}
						/*
						// Handle flag type parameters (boolean)
						if ($arg['type'] === 'flag') {
							$prop_name = str_replace('-', '_', $arg['name']);
							$properties[ $prop_name ] = [
								'type' => 'boolean',
								'description' => $arg['description'] ?? "Flag {$arg['name']}",
								'default' => false
							];
						}*/

						if (!isset($arg['optional']) || !$arg['optional']) {
							$required[] = $prop_name;
						}
				
					}
					$server->register_tool([
						'name' => 'wp_' . str_replace(' ', '_', $command_name) . '_' . str_replace(' ', '_', $subcommand_name),
						'description' => $subcommand_desc,
						'inputSchema' => [
							'type' => 'object',
							'properties' => $properties,
							'required' => $required
						],
						'callable' => function($params) use ($command_name, $subcommand_name, $synopsis_spec) {

							\WP_CLI::debug("Subcommand: " . $subcommand_name . " - Received params: " . print_r($params, true), 'ai');
							
							$args = [];
							$assoc_args = [];
							
							// Process positional arguments first
							foreach ($synopsis_spec as $arg) {
								if ($arg['type'] === 'positional') {
									$prop_name = str_replace('-', '_', $arg['name']);
									if (isset($params[$prop_name])) {
										$args[] = $params[$prop_name];
									}
								}
							}
							
							// Process associative arguments and flags
							foreach ($params as $key => $value) {
								// Skip positional args and dummy param
								if ($key === 'dummy') {
									continue;
								}
								
								// Check if this is an associative argument
								foreach ($synopsis_spec as $arg) {
									if (($arg['type'] === 'assoc' || $arg['type'] === 'flag') && 
										str_replace('-', '_', $arg['name']) === $key) {
										$assoc_args[str_replace('_', '-', $key)] = $value;
										break;
									}
								}
							}
		
							ob_start();
							WP_CLI::run_command( array_merge([ $command_name, $subcommand_name], $args), $assoc_args);
							return ob_get_clean();
							
						}
					]);		
				}
			}
		}

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
