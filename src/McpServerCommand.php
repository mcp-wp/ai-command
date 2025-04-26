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
	 * @param string[] $args Indexed array of positional arguments.
	 * @param array<string, string> $assoc_args Associative arguments.
	 */
	public function list_( $args, $assoc_args ): void {
		$_servers = $this->get_config()->get_servers();

		$servers = [];

		foreach ( $_servers as $server ) {
			// Support features like --status=active.
			foreach ( array_keys( $server ) as $field ) {
				if ( isset( $assoc_args[ $field ] ) && ! in_array( $server[ $field ], array_map( 'trim', explode( ',', $assoc_args[ $field ] ) ), true ) ) {
					continue 2;
				}
			}

			$servers[] = $server;
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
	 * @param string[] $args Indexed array of positional arguments.
	 */
	public function add( $args ): void {
		if ( $this->get_config()->has_server( $args[0] ) ) {
			WP_CLI::error( 'Server already exists.' );
		} else {
			$this->get_config()->add_server(
				[
					'name'   => $args[0],
					'server' => $args[1],
					'status' => 'active',
				]
			);

			WP_CLI::success( 'Server added.' );
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
	 * @param string[] $args Indexed array of positional arguments.
	 * @param array<string, string> $assoc_args Associative arguments.
	 */
	public function remove( $args, $assoc_args ): void {
		$all = (bool) Utils\get_flag_value( $assoc_args, 'all', false );

		if ( ! $all && empty( $args ) ) {
			WP_CLI::error( 'Please specify one or more servers, or use --all.' );
		}

		$successes = 0;
		$errors    = 0;
		$count     = count( $args );

		foreach ( $args as $server ) {
			if ( ! $this->get_config()->has_server( $server ) ) {
				WP_CLI::warning( "Server '$server' not found." );
				++$errors;
			} else {
				$this->get_config()->remove_server( $server );
				++$successes;
			}
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
	 * @param string[] $args Indexed array of positional arguments.
	 * @param array<string, string> $assoc_args Associative arguments.
	 */
	public function update( $args, array $assoc_args ): void {
		$server = $this->get_config()->get_server( $args[0] );

		if ( null === $server ) {
			WP_CLI::error( "Server '$args[0]' not found." );
			return;
		}

		foreach ( $server as $key => $value ) {
			if ( isset( $assoc_args[ $key ] ) ) {
				$new_value = $assoc_args[ $key ];
				if ( 'status' === $key ) {
					$new_value = 'inactive' === $new_value ? 'inactive' : 'active';
				}
				$server[ $key ] = $new_value;
			}
		}

		$this->get_config()->update_server( $args[0], $server );

		WP_CLI::success( 'Server updated.' );
	}

	/**
	 * Returns a Formatter object based on supplied parameters.
	 *
	 * @param array<string, string> $assoc_args Parameters passed to command. Determines formatting.
	 * @return Formatter
	 * @param-out array<string, string> $assoc_args
	 */
	protected function get_formatter( array &$assoc_args ) {
		return new Formatter(
			// TODO: Fix type.
			// @phpstan-ignore paramOut.type
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
