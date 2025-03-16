<?php

namespace WP_CLI\AiCommand;

class ImageTools {


	public function get_tools(){
		return [
				$this->image_generation_tool()
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
					],
					'required'   => [ 'prompt' ],
				],
				'callable'    => function ( $params ) use ( $client ) {
					return $client->get_image_from_ai_service( $params['prompt'] );
				},
			]
			);
	}



}