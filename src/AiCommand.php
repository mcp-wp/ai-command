<?php

namespace McpWp\AiCommand;

use McpWp\AiCommand\AI\WpAiClient;
use WP_CLI;
use WP_CLI\Utils;
use WP_CLI_Command;

/**
 * AI command class.
 *
 * Allows interacting with an LLM using the WP AI Client.
 */
class AiCommand extends WP_CLI_Command {

	/**
	 * AI prompt.
	 *
	 * ## OPTIONS
	 *
	 * <prompt>
	 * : AI prompt.
	 *
	 * [--skip-wordpress]
	 * : Run command without loading WordPress. (Not implemented yet)
	 *
	 * [--service=<service>]
	 * : Manually specify the AI service to use.
	 * Examples: 'google', 'anthropic', 'openai'.
	 *
	 * [--model=<model>]
	 * : Manually specify the LLM model that should be used.
	 * Examples: 'gemini-2.0-flash', 'gpt-4o', 'claude-sonnet-4-5'.
	 *
	 * ## EXAMPLES
	 *
	 *     # Ask a simple question
	 *     $ wp ai prompt "Explain WordPress in one sentence"
	 *     WordPress is a free and open-source content management system...
	 *
	 *     # Use a specific model
	 *     $ wp ai prompt "Summarize the history of WordPress" --model=gpt-4o
	 *     WordPress was created in 2003...
	 *
	 * @when before_wp_load
	 *
	 * @param string[] $args Indexed array of positional arguments.
	 * @param array<string, string> $assoc_args Associative arguments.
	 */
	public function prompt( array $args, array $assoc_args ): void {
		$with_wordpress = null === Utils\get_flag_value( $assoc_args, 'skip-wordpress' );
		if ( $with_wordpress ) {
			WP_CLI::get_runner()->load_wordpress();
		} else {
			WP_CLI::error( 'Not implemented yet.' );
		}

		// Ensure WP AI Client is available.
		if ( ! class_exists( '\WordPress\AI_Client\AI_Client' ) ) {
			WP_CLI::error( 'This command requires the WP AI Client. Please ensure WordPress 7.0+ or the AI plugin is installed and activated.' );
		}

		$service = Utils\get_flag_value( $assoc_args, 'service' );
		$model   = Utils\get_flag_value( $assoc_args, 'model' );

		$ai_client = new WpAiClient( $service, $model );
		$ai_client->call_ai_service_with_prompt( $args[0] );
	}
}
