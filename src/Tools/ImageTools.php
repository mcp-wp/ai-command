<?php

namespace WP_CLI\AiCommand\Tools;
use WP_CLI\AiCommand\Entity\Tool;
use WP_CLI\AiCommand\custom_tome_log;


class ImageTools {

	protected $client;
	protected $server;

	public function __construct($client, $server) {
		$this->client = $client;
		$this->server = $server;
	}


	public function get_tools(){
		return [
				$this->image_generation_tool(),
				$this->image_modification_tool()
		];
	}

	public function image_generation_tool() {
		return new Tool(
			[
				'name' => 'generate_image',
				'description' => 'Generates an image',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'prompt' => [
							'type'        => 'string',
							'description' => 'The prompt for generating the image.',
						],
						'title' => [
							'type'        => 'string',
							'description' => 'the title of the image, also used in filename.',
						],
					],
					'required'   => [ 'prompt' ],
				],
				'callable'    => function ( $params ) {
					if (empty($params['title'])) {
						$params['title'] = $params['prompt'];
					}
					return $this->client->get_image_from_ai_service( $params['prompt'], $params['title'] );
				},
			]
			);
	}

	public function image_modification_tool() {

		return new Tool(
					[
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
							$media_uri      = 'media://' . $params['media_id'];
							$media_resource = $this->server->get_resource_data( $media_uri );
							return $this->client->modify_image_with_ai( $params['prompt'], $media_resource );
						},
					]
					);

	}
}
