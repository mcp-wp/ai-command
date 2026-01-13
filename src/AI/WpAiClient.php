<?php

namespace McpWp\AiCommand\AI;

use Exception;
use WP_CLI;
use function cli\prompt;

/**
 * WP AI Client wrapper class.
 *
 * Provides an adapter for the WP AI Client library.
 */
class WpAiClient {
	/**
	 * @param string|null $service Service to use.
	 * @param string|null $model   Model to use.
	 */
	public function __construct(
		private readonly ?string $service,
		private readonly ?string $model
	) {}

	/**
	 * Calls AI service with a prompt.
	 *
	 * @param string $prompt The prompt to send.
	 */
	public function call_ai_service_with_prompt( string $prompt ): void {
		try {
			// Initialize WP AI Client if not already done.
			if ( ! did_action( 'init' ) ) {
				// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- This is a core WordPress hook.
				do_action( 'init' );
			}

			\WordPress\AI_Client\AI_Client::init();

			// Create a prompt builder.
			$prompt_builder = \WordPress\AI_Client\AI_Client::prompt( $prompt );

			// Apply model preference if specified.
			if ( $this->service && $this->model ) {
				$prompt_builder = $prompt_builder->using_model_preference( [ $this->service, $this->model ] );
			} elseif ( $this->model ) {
				// If only model is specified without a service, try common providers.
				// This provides a reasonable fallback that works with most configurations.
				// The WP AI Client will automatically use the first available provider
				// that has the specified model and is properly configured.
				$prompt_builder = $prompt_builder->using_model_preference(
					[ 'anthropic', $this->model ],
					[ 'openai', $this->model ],
					[ 'google', $this->model ]
				);
			}

			// Generate text response.
			$text = $prompt_builder->generate_text();

			// Output the response.
			WP_CLI::line( WP_CLI::colorize( "%G$text%n" ) );

			// Keep the session open for follow-up questions.
			$this->continue_conversation();

		} catch ( Exception $e ) {
			WP_CLI::error( $e->getMessage() );
		}
	}

	/**
	 * Continues the conversation with follow-up prompts.
	 */
	private function continue_conversation(): void {
		$user_response = prompt( '', false, '' );

		if ( empty( $user_response ) ) {
			return;
		}

		try {
			$prompt_builder = \WordPress\AI_Client\AI_Client::prompt( $user_response );

			if ( $this->service && $this->model ) {
				$prompt_builder = $prompt_builder->using_model_preference( [ $this->service, $this->model ] );
			}

			$text = $prompt_builder->generate_text();

			WP_CLI::line( WP_CLI::colorize( "%G$text%n" ) );

			$this->continue_conversation();
		} catch ( Exception $e ) {
			WP_CLI::error( $e->getMessage() );
		}
	}
}
