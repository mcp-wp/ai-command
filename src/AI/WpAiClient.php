<?php

namespace McpWp\AiCommand\AI;

use Exception;
use InvalidArgumentException;
use WP_CLI;
use function cli\menu;
use function cli\prompt;

/**
 * WP AI Client wrapper class.
 *
 * Provides an adapter for the WP AI Client library.
 *
 * @phpstan-type ToolDefinition array{name: string, description: string|null, parameters: array<string, array<string, mixed>>, server: string, callback: callable}
 */
class WpAiClient {
	private bool $needs_approval = true;

	/**
	 * @param array       $tools         List of tools.
	 * @param bool        $approval_mode Whether tool usage needs to be approved.
	 * @param string|null $service       Service to use.
	 * @param string|null $model         Model to use.
	 *
	 * @phpstan-param ToolDefinition[] $tools
	 */
	public function __construct(
		private readonly array $tools,
		private readonly bool $approval_mode,
		private readonly ?string $service,
		private readonly ?string $model
	) {}

	/**
	 * Calls a given tool.
	 *
	 * @param string $tool_name Tool name.
	 * @param mixed $tool_args Tool args.
	 * @return mixed
	 */
	private function call_tool( string $tool_name, mixed $tool_args ): mixed {
		foreach ( $this->tools as $tool ) {
			if ( $tool_name === $tool['name'] ) {
				return call_user_func( $tool['callback'], $tool_args );
			}
		}

		throw new InvalidArgumentException( 'Tool "' . $tool_name . '" not found.' );
	}

	/**
	 * Returns the name of the server a given tool is coming from.
	 *
	 * @param string $tool_name Tool name.
	 * @return mixed
	 */
	private function get_tool_server_name( string $tool_name ): mixed {
		foreach ( $this->tools as $tool ) {
			if ( $tool_name === $tool['name'] ) {
				return $tool['server'];
			}
		}

		throw new InvalidArgumentException( 'Tool "' . $tool_name . '" not found.' );
	}

	/**
	 * Calls AI service with a prompt.
	 *
	 * @param string $prompt The prompt to send.
	 */
	public function call_ai_service_with_prompt( string $prompt ): void {
		try {
			// Initialize WP AI Client if not already done.
			if ( ! did_action( 'init' ) ) {
				do_action( 'init' );
			}

			\WordPress\AI_Client\AI_Client::init();

			// Create a prompt builder.
			$prompt_builder = \WordPress\AI_Client\AI_Client::prompt( $prompt );

			// Apply model preference if specified.
			if ( $this->service && $this->model ) {
				$prompt_builder = $prompt_builder->using_model_preference( [ $this->service, $this->model ] );
			} elseif ( $this->model ) {
				// If only model is specified, try to use it as a preference.
				$prompt_builder = $prompt_builder->using_model_preference( [ 'anthropic', $this->model ], [ 'openai', $this->model ], [ 'google', $this->model ] );
			}

			// Generate text response.
			$text = $prompt_builder->generate_text();

			// Output the response.
			WP_CLI::line( WP_CLI::colorize( "%G$text%n" ) );

			// Keep the session open for follow-up questions.
			$this->continue_conversation( $prompt, $text );

		} catch ( Exception $e ) {
			WP_CLI::error( $e->getMessage() );
		}
	}

	/**
	 * Continues the conversation with follow-up prompts.
	 *
	 * @param string $initial_prompt The initial prompt.
	 * @param string $response The AI response.
	 */
	private function continue_conversation( string $initial_prompt, string $response ): void {
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

			$this->continue_conversation( $user_response, $text );
		} catch ( Exception $e ) {
			WP_CLI::error( $e->getMessage() );
		}
	}
}
