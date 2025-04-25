<?php

namespace McpWp\AiCommand\AI;

use Exception;
use Felix_Arntz\AI_Services\Services\API\Enums\AI_Capability;
use Felix_Arntz\AI_Services\Services\API\Enums\Content_Role;
use Felix_Arntz\AI_Services\Services\API\Types\Content;
use Felix_Arntz\AI_Services\Services\API\Types\Parts;
use Felix_Arntz\AI_Services\Services\API\Types\Parts\Function_Call_Part;
use Felix_Arntz\AI_Services\Services\API\Types\Parts\Text_Part;
use Felix_Arntz\AI_Services\Services\API\Types\Tools;
use Felix_Arntz\AI_Services\Services\Contracts\Generative_AI_Model;
use Felix_Arntz\AI_Services\Services\Contracts\With_Text_Generation;
use InvalidArgumentException;
use WP_CLI;
use function cli\menu;
use function cli\prompt;

/**
 * AI client class.
 *
 * @phpstan-type ToolDefinition array{name: string, description: string, parameters: array<string, array<string, mixed>>, server: string, callback: callable}
 */
class AiClient {
	private bool $needs_approval = true;

	/**
	 * @param array       $tools         List of tools.
	 * @param bool        $approval_mode Whether tool usage needs to be approved.
	 * @param string|null $model         Model to use.
	 *
	 * @phpstan-param ToolDefinition[] $tools
	 */
	public function __construct(
		private readonly array $tools,
		private readonly bool $approval_mode,
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

	public function call_ai_service_with_prompt( string $prompt ): void {
		$parts = new Parts();
		$parts->add_text_part( $prompt );
		$content = new Content( Content_Role::USER, $parts );

		$this->call_ai_service( [ $content ] );
	}

	/**
	 * Calls AI service with given contents.
	 *
	 * @param Content[] $contents Contents to send to AI.
	 * @return void
	 */
	private function call_ai_service( $contents ): void {
		// See https://github.com/felixarntz/ai-services/issues/25.
		// Temporarily ignore error because eventually this should not be needed anymore.
		// @phpstan-ignore function.notFound
		add_filter(
			'map_meta_cap',
			static function () {
				return [ 'exist' ];
			}
		);

		$new_contents = $contents;

		$tools = new Tools();
		if ( ! empty( $this->tools ) ) {
			$tools->add_function_declarations_tool( $this->tools );
		}

		try {
			$service = ai_services()->get_available_service(
				[
					'capabilities' => [
						AI_Capability::MULTIMODAL_INPUT,
						AI_Capability::TEXT_GENERATION,
						AI_Capability::FUNCTION_CALLING,
					],
				]
			);

			/**
			 * Text generation model.
			 *
			 * @var With_Text_Generation&Generative_AI_Model $model
			 */
			$model = $service
				->get_model(
					[
						'feature'      => 'text-generation',
						'model'        => $this->model,
						'tools'        => $tools,
						'capabilities' => [
							AI_Capability::MULTIMODAL_INPUT,
							AI_Capability::TEXT_GENERATION,
							AI_Capability::FUNCTION_CALLING,
						],
					],
					[
						'options' => [
							'timeout' => 6000,
						],
					]
				);

				$candidates = $model->generate_text( $contents );

			$text = '';

			$parts = $candidates->get( 0 )->get_content()?->get_parts() ?? new Parts();

			foreach ( $parts as $part ) {
				if ( $part instanceof Text_Part ) {
					if ( '' !== $text ) {
						$text .= "\n\n";
					}
					$text .= $part->get_text();
				} elseif ( $part instanceof Function_Call_Part ) {
					WP_CLI::debug( "Suggesting tool call: '{$part->get_name()}'.", 'ai-command' );

					// Need to repeat the function call part.
					$parts = new Parts();
					$parts->add_function_call_part( $part->get_id(), $part->get_name(), $part->get_args() );
					$new_contents[] = new Content( Content_Role::MODEL, $parts );

					$can_call_tool = true;

					if ( $this->approval_mode && $this->needs_approval ) {
						WP_CLI::line(
							WP_CLI::colorize(
								sprintf(
									"Run tool \"%%b%s%%n\" from \"%%b%s%%n\"?\n%%yNote:%%n Running tools from untrusted servers could have unintended consequences. Review each action carefully before approving.",
									$part->get_name(),
									$this->get_tool_server_name( $part->get_name() )
								)
							)
						);
						$result = menu(
							[
								'y' => 'Allow once',
								'a' => 'Always allow',
								'n' => 'Deny once',
							],
							'y',
							'Run tool? Choose between 1-3',
						);

						if ( 'n' === $result ) {
							$can_call_tool = false;
						} elseif ( 'a' === $result ) {
							$this->needs_approval = false;
						}
					}

					if ( $can_call_tool ) {
						$function_result = $this->call_tool(
							$part->get_name(),
							$part->get_args()
						);

						// Debugging.
						// TODO: Need to figure out correct format so LLM picks it up.
						$function_result = [
							'name'    => $part->get_name(),
							'content' => $function_result['text'],
						];

						WP_CLI::debug( "Called the '{$part->get_name()}' tool.", 'ai-command' );

						$parts = new Parts();
						$parts->add_function_response_part( $part->get_id(), $part->get_name(), $function_result );
						$content        = new Content( Content_Role::USER, $parts );
						$new_contents[] = $content;
					} else {
						WP_CLI::debug( "Function call denied:'{$part->get_name()}'.", 'ai-command' );
					}
				}
			}

			if ( $new_contents !== $contents ) {
				$this->call_ai_service( $new_contents );
				return;
			}

			// Keep the session open to continue chatting.

			WP_CLI::line( WP_CLI::colorize( "%G$text%n " ) );

			$response = prompt( '', false, '' );

			$parts = new Parts();
			$parts->add_text_part( $response );
			$content        = new Content( Content_Role::USER, $parts );
			$new_contents[] = $content;
			$this->call_ai_service( $new_contents );
			return;
		} catch ( Exception $e ) {
			WP_CLI::error( $e->getMessage() );
		}
	}
}
