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
use InvalidArgumentException;
use WP_CLI;
use function cli\menu;
use function cli\prompt;

class AiClient {
	private $needs_approval = true;

	public function __construct( private readonly array $tools, private readonly bool $approval_mode ) {}

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

	public function call_ai_service_with_prompt( string $prompt ) {
		$parts = new Parts();
		$parts->add_text_part( $prompt );
		$content = new Content( Content_Role::USER, $parts );

		$this->call_ai_service( [ $content ] );
	}

	private function call_ai_service( $contents ) {
		// See https://github.com/felixarntz/ai-services/issues/25.
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

			if ( $service->get_service_slug() === 'openai' ) {
				$model = 'gpt-4o';
			} else {
				$model = 'gemini-2.0-flash';
			}

			$candidates = $service
				->get_model(
					[
						'feature'      => 'text-generation',
						'model'        => $model,
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
				)
				->generate_text( $contents );

			$text = '';
			foreach ( $candidates->get( 0 )->get_content()->get_parts() as $part ) {
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
							sprintf(
								"Run tool \"%s\" from \"%s\"?\nNote: Running tools from untrusted servers could have unintended consequences. Review each action carefully before approving.",
								$part->get_name(),
								$this->get_tool_server_name( $part->get_name() )
							)
						);
						$result = menu(
							[
								'y' => 'Allow once',
								'a' => 'Always allow',
								'n' => 'Deny',
							],
							'y',
							'Run tool?',
						);

						if ( 'n' === $result ) {
							$can_call_tool = false;
						} elseif ( 'a' === $result ) {
							$this->needs_approval = false;
						}

						var_dump( '$result', $result, $this->needs_approval );
					}

					var_dump( 'needs approval', $this->needs_approval );

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

			WP_CLI::line( $text );

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
