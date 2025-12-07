<?php

namespace McpWp\AiCommand;

use WP_CLI;
use WP_CLI\Utils;
use WP_CLI_Command;

/**
 * Credentials command class.
 *
 * Manages AI provider credentials for the WP AI Client.
 */
class CredentialsCommand extends WP_CLI_Command {

	/**
	 * List all configured AI provider credentials.
	 *
	 * ## EXAMPLES
	 *
	 *     # List all credentials
	 *     $ wp ai credentials list
	 *     +-----------+------------+
	 *     | Provider  | Status     |
	 *     +-----------+------------+
	 *     | openai    | configured |
	 *     | anthropic | configured |
	 *     +-----------+------------+
	 *
	 * @when before_wp_load
	 *
	 * @param string[] $args Indexed array of positional arguments.
	 * @param array<string, string> $assoc_args Associative arguments.
	 */
	public function list( array $args, array $assoc_args ): void {
		$this->ensure_wp_ai_client_available();

		WP_CLI::get_runner()->load_wordpress();

		$credentials = get_option( 'wp_ai_client_provider_credentials', [] );

		if ( empty( $credentials ) ) {
			WP_CLI::log( 'No credentials configured.' );
			return;
		}

		$rows = [];
		foreach ( $credentials as $provider => $data ) {
			$rows[] = [
				'provider' => $provider,
				'status'   => ! empty( $data['api_key'] ) ? 'configured' : 'not configured',
			];
		}

		Utils\format_items( 'table', $rows, [ 'provider', 'status' ] );
	}

	/**
	 * Set credentials for an AI provider.
	 *
	 * ## OPTIONS
	 *
	 * <provider>
	 * : The AI provider to configure (e.g., 'openai', 'anthropic', 'google').
	 *
	 * <api-key>
	 * : The API key for the provider.
	 *
	 * ## EXAMPLES
	 *
	 *     # Set OpenAI credentials
	 *     $ wp ai credentials set openai sk-proj-...
	 *     Success: Credentials for 'openai' saved.
	 *
	 * @when before_wp_load
	 *
	 * @param string[] $args Indexed array of positional arguments.
	 * @param array<string, string> $assoc_args Associative arguments.
	 */
	public function set( array $args, array $assoc_args ): void {
		$this->ensure_wp_ai_client_available();

		WP_CLI::get_runner()->load_wordpress();

		$provider = $args[0];
		$api_key  = $args[1];

		$credentials              = get_option( 'wp_ai_client_provider_credentials', [] );
		$credentials[ $provider ] = [ 'api_key' => $api_key ];

		update_option( 'wp_ai_client_provider_credentials', $credentials );

		WP_CLI::success( "Credentials for '$provider' saved." );
	}

	/**
	 * Delete credentials for an AI provider.
	 *
	 * ## OPTIONS
	 *
	 * <provider>
	 * : The AI provider to remove credentials for.
	 *
	 * ## EXAMPLES
	 *
	 *     # Delete OpenAI credentials
	 *     $ wp ai credentials delete openai
	 *     Success: Credentials for 'openai' deleted.
	 *
	 * @when before_wp_load
	 *
	 * @param string[] $args Indexed array of positional arguments.
	 * @param array<string, string> $assoc_args Associative arguments.
	 */
	public function delete( array $args, array $assoc_args ): void {
		$this->ensure_wp_ai_client_available();

		WP_CLI::get_runner()->load_wordpress();

		$provider = $args[0];

		$credentials = get_option( 'wp_ai_client_provider_credentials', [] );

		if ( ! isset( $credentials[ $provider ] ) ) {
			WP_CLI::error( "No credentials found for '$provider'." );
		}

		unset( $credentials[ $provider ] );
		update_option( 'wp_ai_client_provider_credentials', $credentials );

		WP_CLI::success( "Credentials for '$provider' deleted." );
	}

	/**
	 * Ensures the WP AI Client is available.
	 */
	private function ensure_wp_ai_client_available(): void {
		if ( ! class_exists( '\WordPress\AI_Client\AI_Client' ) ) {
			WP_CLI::error( 'The WP AI Client is not available. Please ensure the WP AI plugin is installed and activated.' );
		}
	}
}
