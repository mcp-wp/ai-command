<?php

namespace McpWp\AiCommand\MCP\Servers\WP_CLI\Tools;

use McpWp\MCP\Server;
use Psr\Log\LoggerInterface;
use WP_CLI;
use WP_CLI\SynopsisParser;
use function WP_CLI\Dispatcher\get_path;

/**
 * @phpstan-import-type ToolDefinition from Server
 */
readonly class CliCommands {
	public function __construct( private LoggerInterface $logger ) {
	}

	/**
	 * @param WP_CLI\Dispatcher\CompositeCommand $command Command instance.
	 * @return array<WP_CLI\Dispatcher\CompositeCommand>
	 */
	private function get_commands( WP_CLI\Dispatcher\CompositeCommand $command ): array {
		if ( WP_CLI::get_runner()->is_command_disabled( $command ) ) {
			return [];
		}

		// Value is different if it's a RootCommand instance.
		// @phpstan-ignore booleanNot.alwaysFalse
		if ( ! $command->can_have_subcommands() ) {
			return [ $command ];
		}

		$commands = [];

		/**
		 * @var WP_CLI\Dispatcher\CompositeCommand $subcommand
		 */
		foreach ( $command->get_subcommands() as $subcommand ) {
			array_push( $commands, ...$this->get_commands( $subcommand ) );
		}

		return $commands;
	}

	/**
	 * Returns a list of tools.
	 *
	 * @return array<int, ToolDefinition> Tools.
	 */
	public function get_tools(): array {
		$commands = $this->get_commands( WP_CLI::get_root_command() );

		$tools = [];

		/**
		 * Command class.
		 *
		 * @var WP_CLI\Dispatcher\RootCommand|WP_CLI\Dispatcher\Subcommand $command
		 */
		foreach ( $commands as $command ) {
			$command_name = implode( ' ', get_path( $command ) );

			$command_desc     = $command->get_shortdesc();
			$command_synopsis = $command->get_synopsis();

			/**
			 * Parsed synopsys.
			 *
			 * @var array<int, array{optional?: bool, type: string, repeating: bool, name: string}> $synopsis_spec
			 */
			$synopsis_spec = SynopsisParser::parse( $command_synopsis );

			$properties = [];
			$required   = [];

			$this->logger->debug( "Synopsis for command: \"$command_name\"" . ' - ' . print_r( $command_synopsis, true ) );

			foreach ( $synopsis_spec as $arg ) {
				if ( 'positional' === $arg['type'] || 'assoc' === $arg['type'] ) {
					$prop_name                = str_replace( '-', '_', $arg['name'] );
					$properties[ $prop_name ] = [
						'type'        => 'string',
						'description' => "Parameter {$arg['name']}",
					];

					if ( ! isset( $arg['optional'] ) || ! $arg['optional'] ) {
						$required[] = $prop_name;
					}
				}
			}

			if ( empty( $properties ) ) {
				// Some commands such as "wp cache flush" don't take any parameters,
				// but the MCP SDK doesn't seem to like empty $properties.
				$properties['dummy'] = [
					'type'        => 'string',
					'description' => 'Dummy parameter',
				];
			}

			$tool = [
				'name'        => 'wp_cli_' . str_replace( ' ', '_', $command_name ),
				'description' => $command_desc,
				'inputSchema' => [
					'type'       => 'object',
					'properties' => $properties,
					'required'   => $required,
				],
				'callback'    => function ( $params ) use ( $command_name, $synopsis_spec ) {
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
		}

		return $tools;
	}
}
