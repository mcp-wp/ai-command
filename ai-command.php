<?php

namespace WP_CLI\AiCommand;

use WP_CLI\AiCommand\ToolRepository\CollectionToolRepository;
use WP_CLI;

if ( ! class_exists( '\WP_CLI' ) ) {
	return;
}

$ai_command_autoloader = __DIR__ . '/vendor/autoload.php';

if ( file_exists( $ai_command_autoloader ) ) {
	require_once $ai_command_autoloader;
}

WP_CLI::add_command( 'ai', function ( $args, $assoc_args ) {
	$server = new MCP\Server();
	$client = new MCP\Client($server);

	$tools = new ToolCollection();

	// TODO Register your tool here and add it to the collection

	$image_tools = new ImageTools($client);

	foreach($image_tools->get_tools() as $tool){
		$tools->add($tool);
	}


	// WordPress REST calls
	$rest_tools = new MapRESTtoMCP();

	foreach( $rest_tools->map_rest_to_mcp() as $tool ) {
		$tools->add( $tool );
	}

	$ai_command = new AiCommand(
		new CollectionToolRepository( $tools ),
		$server,
		$client
	);
	$ai_command( $args, $assoc_args );
} );
