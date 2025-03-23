<?php

namespace WP_CLI\AiCommand\MCP\Servers\WordPress\Tools;

use Mcp\Types\TextContent;

readonly class Dummy {

	public function get_tools(): array {
		$tools = [];

		$tools[] = [
			'name'        => 'greet-user',
			'description' => 'Greet a given user by their name',
			'inputSchema' => [
				'type'       => 'object',
				'properties' => [
					'name' => [
						'type'        => 'string',
						'description' => 'Name',
					],
				],
				'required'   => [ 'name' ],
			],
			'callable'    => static function ( $arguments ) {
				$name = $arguments['name'];

				return new TextContent(
					"Hello my friend, $name"
				);
			},
		];

		return $tools;
	}
}
