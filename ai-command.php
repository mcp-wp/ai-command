<?php

namespace McpWp\AiCommand;

use WP_CLI;

if ( ! class_exists( '\WP_CLI' ) ) {
	return;
}

if ( file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
		require_once __DIR__ . '/vendor/autoload.php';
}

WP_CLI::add_command( 'ai', AiCommand::class );
WP_CLI::add_command( 'mcp prompt', AiCommand::class );
WP_CLI::add_command( 'mcp server', McpServerCommand::class );
