<?php

namespace WP_CLI\AiCommand\MCP\Servers\WordPress;

use Mcp\Types\Resource;
use Mcp\Types\ResourceTemplate;
use WP_CLI\AiCommand\MCP\Server;
use WP_CLI\AiCommand\MCP\Servers\WordPress\Tools\CommunityEvents;
use WP_CLI\AiCommand\MCP\Servers\WordPress\Tools\Dummy;
use WP_CLI\AiCommand\MCP\Servers\WordPress\Tools\ImageTools;
use WP_CLI\AiCommand\MCP\Servers\WordPress\Tools\RestApi;

class WordPress extends Server {
	public function __construct() {
		parent::__construct( 'WordPress' );

		$all_tools = [
			...( new RestApi( $this->logger ) )->get_tools(),
			...( new CommunityEvents() )->get_tools(),
			...( new Dummy() )->get_tools(),
			...( new ImageTools() )->get_tools(),
		];

		/**
		 * Filters all the tools exposed by the WordPress MCP server.
		 *
		 * @param array $all_tools MCP tools.
		 */
		$all_tools = apply_filters( 'ai_command_wordpress_tools', $all_tools );

		foreach ( $all_tools as $tool ) {
			$this->register_tool( $tool );
		}

		/**
		 * Fires after tools have been registered in the WordPress MCP server.
		 *
		 * Can be used to register additional tools.
		 *
		 * @param Server $server WordPress MCP server instance.
		 */
		do_action( 'ai_command_wordpress_tools_loaded', $this );

		$this->register_resource(
			new Resource(
				'Greeting Text',
				'example://greeting',
				'A simple greeting message',
				'text/plain'
			)
		);

		$this->register_resource_template(
			new ResourceTemplate(
				'Attachment',
				'media://{id}',
				'WordPress attachment',
				'application/octet-stream'
			)
		);
	}
}
