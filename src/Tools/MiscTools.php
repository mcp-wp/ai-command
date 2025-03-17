<?php

namespace WP_CLI\AiCommand\Tools;
use WP_CLI\AiCommand\Entity\Tool;

class MiscTools {

	private $server;

	public function __construct( $server ) {
		$this->server = $server;
	}

	public function get_tools(){
		$tools = [];

		$tools[] =  new Tool( [
			'name'        => 'list_tools',
			'description' => 'Lists all available tools with their descriptions.',
			'inputSchema' => [
					'type'       => 'object', // Object type for input
					'properties' => [
						'placeholder'    => [
							'type'        => 'integer',
							'description' => '',
						]
					],
					'required'   => [],       // No required fields
			],
			'callable'    => function () {
					// Get all capabilities
					$capabilities = $this->server->get_capabilities();

					// Prepare a list of tools with their descriptions
					$tool_list = 'Return this to the user as a bullet list with each tool name and description on a new line. \n\n';
					$tool_list .= print_r($capabilities['methods'], true);

					// Return the formatted string of tools with descriptions
					return $tool_list;
			},
		] );

		return $tools;
	}
}
