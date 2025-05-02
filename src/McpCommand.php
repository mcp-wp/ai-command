<?php

namespace McpWp\AiCommand;

use Exception;
use Mcp\Server\Server;
use Mcp\Server\Transport\StdioServerTransport;
use McpWp\AiCommand\MCP\ProxySession;
use McpWp\AiCommand\Utils\CliLogger;
use McpWp\AiCommand\Utils\McpConfig;
use WP_CLI;
use WP_CLI_Command;

/**
 * MCP command.
 *
 * Manage MCP servers for use with WP-CLI and proxy requests to servers using the HTTP transport.
 */
class McpCommand extends WP_CLI_Command {
	/**
	 * Proxy MCP requests to a given server.
	 *
	 * ## OPTIONS
	 *
	 * <server>
	 * : Name of an existing server to proxy requests to.
	 *
	 * ## EXAMPLES
	 *
	 *     # Add server from URL.
	 *     $ wp mcp server add "mywpserver" "https://example.com/wp-json/mcp/v1/mcp"
	 *     Success: Server added.
	 *
	 *     # Proxy requests to server
	 *     $ wp mcp proxy "mywpserver"
	 *
	 * @when before_wp_load
	 *
	 * @param string[] $args Indexed array of positional arguments.
	 */
	public function proxy( $args ): void {
		$server = $this->get_config()->get_server( $args[0] );

		if ( null === $server ) {
			WP_CLI::error( 'Server does not exist.' );
			return;
		}

		$url = $server['server'];

		if ( ! str_starts_with( $url, 'http://' ) && ! str_starts_with( $url, 'https://' ) ) {
			WP_CLI::error( 'Server is not using HTTP transport.' );
			return;
		}

		$logger = new CliLogger();

		$server = new Server( $args[0], $logger );

		try {
			$transport = StdioServerTransport::create();

			$proxy_session = new ProxySession(
				$url,
				$transport,
				$server->createInitializationOptions(),
				$logger
			);

			$server->setSession( $proxy_session );

			$proxy_session->registerHandlers( $server->getHandlers() );
			$proxy_session->registerNotificationHandlers( $server->getNotificationHandlers() );

			$proxy_session->start();

			$logger->info( 'Server started' );

		} catch ( Exception $e ) {
			$logger->error( 'Proxy error: ' . $e->getMessage() );
		} finally {
			if ( isset( $proxy_session ) ) {
				$proxy_session->stop();
			}
			if ( isset( $transport ) ) {
				$transport->stop();
			}
		}
	}

	/**
	 * Returns an McpConfig instance.
	 *
	 * @return McpConfig Config instance.
	 */
	protected function get_config(): McpConfig {
		return new McpConfig();
	}
}
