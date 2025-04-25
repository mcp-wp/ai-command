<?php

namespace McpWp\AiCommand\Utils;

use WP_CLI\Utils;
use WP_CLI_Command;

/**
 * McpConfig class.
 *
 * @phpstan-type McpConfigServer array{name: string, server: string, status: string}
 * @phpstan-type McpConfigData array{servers: McpConfigServer[]}
 */
class McpConfig extends WP_CLI_Command {
	/**
	 * Returns a list of all servers.
	 *
	 * @return McpConfigServer[] List of servers.
	 */
	public function get_servers() {
		return $this->get_config()['servers'];
	}

	/**
	 * Returns a server with the given name.
	 *
	 * @param string $name Server name.
	 * @return McpConfigServer|null Server if found, null otherwise.
	 */
	public function get_server( string $name ): ?array {
		$config = $this->get_config();
		foreach ( $config['servers'] as $server ) {
			if ( $name === $server['name'] ) {
				return $server;
			}
		}

		return null;
	}

	/**
	 * Determines whether a server with the given name exists in the config.
	 *
	 * @param string $name Server name.
	 * @return bool Whether the server exists.
	 */
	public function has_server( string $name ): bool {
		return $this->get_server( $name ) !== null;
	}

	/**
	 * Adds a new server to the list.
	 *
	 * @param McpConfigServer $server Server data.
	 * @return void
	 */
	public function add_server( array $server ): void {
		$config              = $this->get_config();
		$config['servers'][] = $server;
		$this->update_config( $config );
	}

	/**
	 * Updates a specific server in the config.
	 * @param string          $name   Server name.
	 * @param McpConfigServer $server Server data.
	 * @return void
	 */
	public function update_server( string $name, array $server ): void {
		$config = $this->get_config();
		foreach ( $config['servers'] as &$_server ) {
			if ( $name === $_server['name'] ) {
				$_server = $server;
			}
		}

		unset( $_server );

		$this->update_config( $config );
	}

	/**
	 * Removes a given server from the config.
	 *
	 * @param string $name Server name.
	 * @return void
	 */
	public function remove_server( string $name ): void {
		$config = $this->get_config();

		foreach ( $config['servers'] as $key => $server ) {
			if ( $name === $server['name'] ) {
				unset( $config['servers'][ $key ] );
			}
		}

		$this->update_config( $config );
	}

	/**
	 * Returns the current MCP config.
	 *
	 * @return array Config data.
	 * @phpstan-return McpConfigData
	 */
	protected function get_config() {
		$config_file = Utils\get_home_dir() . '/.wp-cli/ai-command.json';

		if ( ! file_exists( $config_file ) ) {
			return [
				'servers' => [],
			];
		}

		$json_content = file_get_contents( $config_file );

		if ( false === $json_content ) {
			return [
				'servers' => [],
			];
		}

		$config = json_decode( $json_content, true, 512, JSON_THROW_ON_ERROR );

		if ( null === $config ) {
			return [
				'servers' => [],
			];
		}

		/**
		 * Loaded config.
		 *
		 * @var McpConfigData $config
		 */
		return $config;
	}

	/**
	 * Updates the MCP config.
	 *
	 * @param array $new_config Updated config.
	 * @return bool Whether updating was successful.
	 * @phpstan-param McpConfigData $new_config
	 */
	protected function update_config( $new_config ): bool {
		$config_file = Utils\get_home_dir() . '/.wp-cli/ai-command.json';

		if ( ! file_exists( $config_file ) ) {
			touch( $config_file );
		}

		return (bool) file_put_contents( $config_file, json_encode( $new_config, JSON_PRETTY_PRINT ) );
	}
}
