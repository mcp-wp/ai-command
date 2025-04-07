<?php

namespace McpWp\AiCommand;

use McpWp\AiCommand\Utils\McpConfig;
use WP_CLI;
use WP_CLI\Formatter;
use WP_CLI_Command;
use WP_CLI\Utils;

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
	 * [--<field>=<value>]
	 * : Filter results by key=value pairs.
	 *
	 * [--format=<format>]
	 * : Render output in a particular format.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - csv
	 *   - json
	 *   - count
	 * ---
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
	 * @when before_wp_load
	 *
	 * @param array $args Indexed array of positional arguments.
	 * @param array $assoc_args Associative array of associative arguments.
	 */
	public function list_( $args, $assoc_args ): void {
		$config = $this->get_config()->get_config();

		$servers = [];

		foreach ( $config as $name => $server ) {
			// Support features like --status=active.
			foreach ( array_keys( $server ) as $field ) {
				if ( isset( $assoc_args[ $field ] ) && ! in_array( $server[ $field ], array_map( 'trim', explode( ',', $assoc_args[ $field ] ) ), true ) ) {
					continue 2;
				}
			}

			$servers[] = [
				'name'   => $name,
				'server' => $server['server'],
				'status' => $server['status'],
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
	 * @when before_wp_load
	 *
	 * @param array $args Indexed array of positional arguments.
	 */
	public function add( $args ): void {
		$config = $this->get_config()->get_config();

		if ( isset( $config[ $args[0] ] ) ) {
			WP_CLI::error( 'Server already exists.' );
		} else {
			$config[ $args[0] ] = [
				'server' => $args[1],
				'status' => 'active',
			];

			$result = $this->get_config()->update_config( $config );

			if ( ! $result ) {
				WP_CLI::error( 'Could not add server.' );
			} else {
				WP_CLI::success( 'Server added.' );
			}
		}
	}

	/**
	 * Remove one or more MCP servers.
	 *
	 * ## OPTIONS
	 *
	 * [<name>...]
	 * : One or more servers to remove
	 *
	 * [--all]
	 * : Whether to remove all servers.
	 *
	 * ## EXAMPLES
	 *
	 *     # Remove server.
	 *     $ wp mcp server remove "server-filesystem"
	 *     Success: Server removed.
	 *
	 * @when before_wp_load
	 *
	 * @param array $args Indexed array of positional arguments.
	 */
	public function remove( $args, $assoc_args ): void {
		$all = Utils\get_flag_value( $assoc_args, 'all', false );

		if ( ! $all && empty( $args ) ) {
			WP_CLI::error( 'Please specify one or more servers, or use --all.' );
		}

		$config = $this->get_config()->get_config();

		$successes = 0;
		$errors    = 0;
		$count     = count( $args );

		foreach ( $args as $server ) {
			if ( ! array_key_exists( $server, $config ) ) {
				WP_CLI::warning( "Server '$server' not found." );
				++$errors;
			} else {
				unset( $config[ $server ] );
				++$successes;
			}
		}

		$result = $this->get_config()->update_config( $config );

		if ( ! $result ) {
			$successes = 0;
			$errors    = $count;
		}

		Utils\report_batch_operation_results( 'server', 'remove', $count, $successes, $errors );
	}

	/**
	 * Update an MCP server.
	 *
	 * ## OPTIONS
	 *
	 * <name>
	 * : Name of the server.
	 *
	 * --<field>=<value>
	 * : One or more fields to update.
	 *
	 * ## EXAMPLES
	 *
	 *     # Remove server.
	 *     $ wp mcp server update "server-filesystem" --status=inactive
	 *     Success: Server updated.
	 *
	 * @when before_wp_load
	 *
	 * @param array $args Indexed array of positional arguments.
	 */
	public function update( $args, $assoc_args ): void {
		$config = $this->get_config()->get_config();

		if ( ! isset( $config[ $args[0] ] ) ) {
			WP_CLI::error( "Server '$args[0]' not found." );
		}

		foreach ( $config[ $args[0] ] as $key => $value ) {
			if ( isset( $assoc_args[ $key ] ) ) {
				if ( 'status' === $key ) {
					$value = 'inactive' === $value ? 'active' : 'inactive';
				}
				$config[ $args[0] ][ $key ] = $value;
			}
		}

		$result = $this->get_config()->update_config( $config );

		if ( ! $result ) {
			WP_CLI::error( 'Could not update server.' );
		} else {
			WP_CLI::success( 'Server updated.' );
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
				'status',
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
