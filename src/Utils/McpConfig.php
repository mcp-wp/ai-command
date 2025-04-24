<?php

namespace McpWp\AiCommand\Utils;

use WP_CLI\Utils;
use WP_CLI_Command;

/**
 * McpConfig class.
 *
 * @phpstan-type McpConfigData array<string, array{server: string, status: string}>
 */
class McpConfig extends WP_CLI_Command {
	/**
	 * Returns the current MCP config.
	 *
	 * @return array Config data.
	 * @phpstan-return McpConfigData
	 */
	public function get_config() {
		$config_file = Utils\get_home_dir() . '/.wp-cli/ai-command.json';

		if ( ! file_exists( $config_file ) ) {
			return [];
		}

		$json_content = file_get_contents( $config_file );
		$config       = json_decode( $json_content, true );
		return null !== $config ? (array) $config : [];
	}

	/**
	 * Updates the MCP config.
	 *
	 * @param array $new_config Updated config.
	 * @return bool Whether updating was successful.
	 * @phpstan-param McpConfigData $new_config
	 */
	public function update_config( $new_config ): bool {
		$config_file = Utils\get_home_dir() . '/.wp-cli/ai-command.json';

		if ( ! file_exists( $config_file ) ) {
			touch( $config_file );
		}

		return (bool) file_put_contents( $config_file, json_encode( $new_config, JSON_PRETTY_PRINT ) );
	}
}
