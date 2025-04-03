<?php

use Symfony\Component\Finder\Finder;

return [
	'prefix'             => 'McpWp\AiCommand_Dependencies',
	'finders'            => [
		Finder::create()
			->files()
			->in( 'vendor/logiscape/mcp-sdk-php/src' ),
		Finder::create()
			->files()
			->in( 'vendor/mcp-wp/mcp-server/src/MCP' ),
	],
	'exclude-namespaces' => [ 'Psr' ],
	'exclude-classes'    => [ 'WP_Community_Events' ],
];
