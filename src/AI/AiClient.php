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
use WP_CLI;

class AiClient {
	/**
	 * @var callable
	 */
	private $tool_callback;

	public function __construct( private readonly array $tools, callable $tool_callback ) {
		$this->tool_callback = $tool_callback;
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
					$function_name = $part->get_name();

					echo "Output generated with the '$function_name' tool:\n";

					// Need to repeat the function call part.
					$parts = new Parts();
					$parts->add_function_call_part( $part->get_id(), $part->get_name(), $part->get_args() );
					$new_contents[] = new Content( Content_Role::MODEL, $parts );

					$function_result = call_user_func(
						$this->tool_callback,
						$part->get_name(),
						$part->get_args()
					);

					// Debugging.
					// TODO: Need to figure out correct format so LLM picks it up.
					$function_result = [
						'name'    => $part->get_name(),
						'content' => $function_result['text'],
					];

					$parts = new Parts();
					$parts->add_function_response_part( $part->get_id(), $part->get_name(), $function_result );
					$content        = new Content( Content_Role::USER, $parts );
					$new_contents[] = $content;
				}
			}

			if ( $new_contents !== $contents ) {
				$this->call_ai_service( $new_contents );
				return;
			}

			// Keep the session open to continue chatting.

			WP_CLI::line( $text );

			$response = \cli\prompt( '', false, '' );

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
