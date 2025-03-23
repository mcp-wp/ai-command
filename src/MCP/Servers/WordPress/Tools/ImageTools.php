<?php

namespace WP_CLI\AiCommand\MCP\Servers\WordPress\Tools;

use Mcp\Types\TextContent;
use WP_CLI\AiCommand\MCP\Servers\WordPress\WpAiClient;

readonly class ImageTools {
	public function get_tools(): array {
		$tools = [];

		$tools[] = [
			'name'        => 'generate_image',
			'description' => 'Generates an image.',
			'inputSchema' => [
				'type'       => 'object',
				'properties' => [
					'prompt' => [
						'type'        => 'string',
						'description' => 'The prompt for generating the image.',
					],
				],
				'required'   => [ 'prompt' ],
			],
			'callable'    => function ( $params ) {
				$client = new WpAiClient();

				return new TextContent(
					$client->get_image_from_ai_service( $params['prompt'] )
				);
			},
		];

		$tools[] = [
			'name'        => 'modify_image',
			'description' => 'Modifies an image with a given image id and prompt.',
			'inputSchema' => [
				'type'       => 'object',
				'properties' => [
					'prompt'   => [
						'type'        => 'string',
						'description' => 'The prompt for generating the image.',
					],
					'media_id' => [
						'type'        => 'string',
						'description' => 'the id of the media element',
					],
				],
				'required'   => [ 'prompt', 'media_id' ],
			],
			'callable'    => function ( $params ) {
				$media_element = [
					'filepath'  => get_attached_file( $params['media_id'] ),
					'mime_type' => get_post_mime_type( $params['media_id'] ),
				];

				$client = new WpAiClient();

				return new TextContent(
					$client->modify_image_with_ai( $params['prompt'], $media_element )
				);
			},
		];

		return $tools;
	}
}
