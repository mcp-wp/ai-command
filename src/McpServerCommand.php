<?php

namespace WP_CLI\AiCommand;

use WP_CLI\AiCommand\Utils\McpConfig;
use WP_CLI\Formatter;
use WP_CLI_Command;

/**
 * MCP Server command.
 *
 * Allows listing, adding, and removing MCP servers for use with the `wp ai` command.
 */
class McpServerCommand extends WP_CLI_Command {
	/**
	 * Lists available MCP servers.
	 *
	 * ## OPTIONS
	 *
	 *  [--format=<format>]
	 *  : Render output in a particular format.
	 *  ---
	 *  default: table
	 *  options:
	 *    - table
	 *    - csv
	 *    - json
	 *    - count
	 *
	 * ## EXAMPLES
	 *
	 *     # Greet the world.
	 *     $ wp mcp server list
	 *     Success: Hello World!
	 *
	 *     # Greet the world.
	 *     $ wp ai "create 10 test posts about swiss recipes and include generated featured images"
	 *     Success: Hello World!
	 *
	 * @subcommand list
	 *
	 * @param array $args Indexed array of positional arguments.
	 * @param array $assoc_args Associative array of associative arguments.
	 */
	public function list_( $args, $assoc_args ): void {
		$config = $this->get_config()->get_config();

		$servers = [];

		foreach ( $config as $name => $server ) {
			$servers[] = [
				'name'   => $name,
				'server' => $server,
			];
		}

		$formatter = $this->get_formatter( $assoc_args );
		$formatter->display_items( $servers );
	}

	/**
	 * Add a new MCP server to the list
	 *
	 * ## OPTIONS
	 *
	 * <name>
	 * : Name for referencing the server later
	 *
	 * <server>
	 * : Server command or URL.
	 *
	 * ## EXAMPLES
	 *
	 *     # Add server from URL.
	 *     $ wp mcp server add "server-github" "https://github.com/mcp"
	 *     Success: Server added.
	 *
	 *     # Add server with command to execute
	 *     $ wp mcp server add "server-filesystem" "npx -y @modelcontextprotocol/server-filesystem /my/allowed/folder/"
	 *     Success: Server added.
	 *
	 * @param array $args Indexed array of positional arguments.
	 */
	public function add( $args ): void {
		$config = $this->get_config()->get_config();

		if ( isset( $config[ $args[0] ] ) ) {
			\WP_CLI::error( 'Server already exists.' );
		} else {
			$config[ $args[0] ] = $args[1];
			$result             = $this->get_config()->update_config( $config );

			if ( ! $result ) {
				\WP_CLI::error( 'Could not add server.' );
			} else {
				\WP_CLI::success( 'Server added.' );
			}
		}
	}

	/**
	 * Remove a new MCP server from the list
	 *
	 * ## OPTIONS
	 *
	 * <name>
	 * : Name of the server to remove
	 *
	 * ## EXAMPLES
	 *
	 *     # Remove server.
	 *     $ wp mcp server remove "server-filesystem"
	 *     Success: Server removed.
	 *
	 * @param array $args Indexed array of positional arguments.
	 */
	public function remove( $args ): void {
		$config = $this->get_config()->get_config();

		if ( ! array_key_exists( $args[0], $config ) ) {
			\WP_CLI::error( 'Server not found.' );
		} else {
			unset( $config[ $args[0] ] );
			$result = $this->get_config()->update_config( $config );

			if ( ! $result ) {
				\WP_CLI::error( 'Could not remove server.' );
			} else {
				\WP_CLI::success( 'Server removed.' );
			}
		}
	}

	/**
	 * Returns a Formatter object based on supplied parameters.
	 *
	 * @param array $assoc_args Parameters passed to command. Determines formatting.
	 * @return Formatter
	 */
	protected function get_formatter( &$assoc_args ) {
		return new Formatter(
			$assoc_args,
			[
				'name',
				'server',
			]
		);
	}

	/**
	 * Returns an McpConfig instance.
	 *
	 * @return McpConfig Config instance.
	 */
	protected function get_config(): McpConfig {
		return new McpConfig();
	}
}
