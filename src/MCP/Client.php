<?php

namespace WP_CLI\AiCommand\MCP;

use Mcp\Client\Client as McpCLient;
use Mcp\Client\ClientSession;
use Mcp\Client\Transport\StdioServerParameters;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;


class Client extends McpCLient {
	private ?ClientSession $session = null;

	private LoggerInterface $logger;

	/**
	 * Client constructor.
	 *
	 * @param LoggerInterface|null $logger PSR-3 compliant logger.
	 */
	public function __construct( ?LoggerInterface $logger = null ) {
		$this->logger = $logger ?? new NullLogger();

		parent::__construct( $this->logger );
	}

	/**
	 * @param string|class-string<Server> $command_or_url Class name, command, or URL.
	 * @param array $args Unused.
	 * @param array|null $env Unused.
	 * @param float|null $read_timeout Unused.
	 * @return ClientSession
	 */
	public function connect(
		string $command_or_url,
		array $args = [],
		?array $env = null,
		?float $read_timeout = null
	): ClientSession {
		if ( class_exists( $command_or_url ) ) {
			/**
			 * @var Server $server
			 */
			$server = new $command_or_url( $this->logger );

			$transport = new InMemoryTransport(
				$server,
				$this->logger
			);

			[$read_stream, $write_stream] = $transport->connect();

			$this->session = new InMemorySession(
				$read_stream,
				$write_stream,
				$this->logger
			);

			$this->session->initialize();

			return $this->session;
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url
		$url_parts = parse_url( $command_or_url );

		if ( isset( $url_parts['scheme'] ) && in_array( strtolower( $url_parts['scheme'] ), [ 'http', 'https' ], true ) ) {
			$options = [
				// Just for local debugging.
				'verify' => false,
			];
			if ( ! empty( $url_parts['user'] ) && ! empty( $url_parts['pass'] ) ) {
				$options['auth'] = [ $url_parts['user'], $url_parts['pass'] ];
			}

			$url = $url_parts['scheme'] . '://' . $url_parts['host'] . $url_parts['path'];

			$transport = new HttpTransport( $url, $options, $this->logger );
			$transport->connect();

			[$read_stream, $write_stream] = $transport->connect();

			// Initialize the client session with the obtained streams
			$this->session = new InMemorySession(
				$read_stream,
				$write_stream,
				$this->logger
			);

			// Initialize the session (e.g., perform handshake if necessary)
			$this->session->initialize();
			$this->logger->info( 'Session initialized successfully' );

			return $this->session;
		}

		return parent::connect( $command_or_url, $args, $env, $read_timeout );
	}
}
