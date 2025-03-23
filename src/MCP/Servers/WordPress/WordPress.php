<?php

namespace WP_CLI\AiCommand\MCP\Servers\WordPress;

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

		foreach ( $all_tools as $tool ) {
			$this->register_tool( $tool );
		}
	}
}
