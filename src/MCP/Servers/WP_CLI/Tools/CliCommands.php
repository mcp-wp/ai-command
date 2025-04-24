<?php

namespace McpWp\AiCommand\MCP\Servers\WP_CLI\Tools;

use McpWp\MCP\Server;
use Psr\Log\LoggerInterface;
use WP_CLI;
use WP_CLI\SynopsisParser;

/**
 * @phpstan-import-type ToolDefinition from Server
 */
readonly class CliCommands {
	public function __construct( private LoggerInterface $logger ) {
	}

	/**
	 * Returns a list of tools.
	 *
	 * @return array<int, ToolDefinition> Tools.
	 */
	public function get_tools(): array {
		// Expose WP-CLI commands as tools
		$commands = [
			'cache',
			'config',
			'core',
			'maintenance-mode',
			'profile',
			'rewrite',
		];

		$tools = [];

		foreach ( $commands as $command ) {
			[$command] = WP_CLI::get_runner()->find_command_to_run( [ $command ] );

			if ( ! is_object( $command ) ) {
				continue;
			}

			$command_name = $command->get_name();

			if ( ! $command->can_have_subcommands() ) {

				$command_desc     = $command->get_shortdesc() ?? "Runs WP-CLI command: $command_name";
				$command_synopsis = $command->get_synopsis();
				$synopsis_spec    = SynopsisParser::parse( $command_synopsis );

				$properties = [];
				$required   = [];

				$properties['dummy'] = [
					'type'        => 'string',
					'description' => 'Dummy parameter',
				];

				$this->logger->debug( 'Synopsis for command: ' . $command_name . ' - ' . print_r( $command_synopsis, true ) );

				foreach ( $command_synopsis as $arg ) {
					if ( 'positional' === $arg['type'] || 'assoc' === $arg['type'] ) {
						$prop_name                = str_replace( '-', '_', $arg['name'] );
						$properties[ $prop_name ] = [
							'type'        => 'string',
							'description' => $arg['description'] ?? "Parameter {$arg['name']}",
						];

						if ( ! isset( $arg['optional'] ) || ! $arg['optional'] ) {
							$required[] = $prop_name;
						}
					}
				}

				$tool = [
					'name'        => 'wp_cli_' . str_replace( ' ', '_', $command_name ),
					'description' => $command_desc,
					'inputSchema' => [
						'type'       => 'object',
						'properties' => $properties,
						'required'   => $required,
					],
					'callable'    => function ( $params ) use ( $command_name, $synopsis_spec ) {
						$args       = [];
						$assoc_args = [];

						// Process positional arguments first
						foreach ( $synopsis_spec as $arg ) {
							if ( 'positional' === $arg['type'] ) {
								$prop_name = str_replace( '-', '_', $arg['name'] );
								if ( isset( $params[ $prop_name ] ) ) {
									$args[] = $params[ $prop_name ];
								}
							}
						}

						// Process associative arguments and flags
						foreach ( $params as $key => $value ) {
							// Skip positional args and dummy param
							if ( 'dummy' === $key ) {
								continue;
							}

							// Check if this is an associative argument
							foreach ( $synopsis_spec as $arg ) {
								if ( ( 'assoc' === $arg['type'] || 'flag' === $arg['type'] ) &&
									str_replace( '-', '_', $arg['name'] ) === $key ) {
									$assoc_args[ str_replace( '_', '-', $key ) ] = $value;
									break;
								}
							}
						}

						ob_start();
						WP_CLI::run_command( array_merge( explode( ' ', $command_name ), $args ), $assoc_args );
						return ob_get_clean();
					},
				];

				$tools[] = $tool;

			} else {

				$this->logger->debug( $command_name . ' subcommands: ' . print_r( $command->get_subcommands(), true ) );

				foreach ( $command->get_subcommands() as $subcommand ) {

					if ( WP_CLI::get_runner()->is_command_disabled( $subcommand ) ) {
						continue;
					}

					$subcommand_name     = $subcommand->get_name();
					$subcommand_desc     = $subcommand->get_shortdesc() ?? "Runs WP-CLI command: $subcommand_name";
					$subcommand_synopsis = $subcommand->get_synopsis();
					$synopsis_spec       = SynopsisParser::parse( $subcommand_synopsis );

					$properties = [];
					$required   = [];

					$properties['dummy'] = [
						'type'        => 'string',
						'description' => 'Dummy parameter',
					];

					foreach ( $synopsis_spec as $arg ) {
						$prop_name = str_replace( '-', '_', $arg['name'] );

						if ( 'positional' === $arg['type'] || 'assoc' === $arg['type'] ) {
							$properties[ $prop_name ] = [
								'type'        => 'string',
								'description' => $arg['description'] ?? "Parameter {$arg['name']}",
							];
						}

						// TODO: Handle flag type parameters (boolean)

						if ( ! isset( $arg['optional'] ) || ! $arg['optional'] ) {
							$required[] = $prop_name;
						}
					}
					$tool = [
						'name'        => 'wp_cli_' . str_replace( ' ', '_', $command_name ) . '_' . str_replace( ' ', '_', $subcommand_name ),
						'description' => $subcommand_desc,
						'inputSchema' => [
							'type'       => 'object',
							'properties' => $properties,
							'required'   => $required,
						],
						'callable'    => function ( $params ) use ( $command_name, $subcommand_name, $synopsis_spec ) {

							$this->logger->debug( 'Subcommand: ' . $subcommand_name . ' - Received params: ' . print_r( $params, true ) );

							$args       = [];
							$assoc_args = [];

							// Process positional arguments first
							foreach ( $synopsis_spec as $arg ) {
								if ( 'positional' === $arg['type'] ) {
									$prop_name = str_replace( '-', '_', $arg['name'] );
									if ( isset( $params[ $prop_name ] ) ) {
										$args[] = $params[ $prop_name ];
									}
								}
							}

							// Process associative arguments and flags
							foreach ( $params as $key => $value ) {
								// Skip positional args and dummy param
								if ( 'dummy' === $key ) {
									continue;
								}

								// Check if this is an associative argument
								foreach ( $synopsis_spec as $arg ) {
									if ( ( 'assoc' === $arg['type'] || 'flag' === $arg['type'] ) &&
										str_replace( '-', '_', $arg['name'] ) === $key ) {
										$assoc_args[ str_replace( '_', '-', $key ) ] = $value;
										break;
									}
								}
							}

							ob_start();
							WP_CLI::run_command( array_merge( [ $command_name, $subcommand_name ], $args ), $assoc_args );
							return ob_get_clean();
						},
					];

					$tools[] = $tool;
				}
			}
		}

		return $tools;
	}
}
