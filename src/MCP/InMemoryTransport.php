<?php

namespace WP_CLI\AiCommand\MCP;

use Exception;
use InvalidArgumentException;
use Mcp\Shared\MemoryStream;
use Mcp\Types\JsonRpcMessage;
use Psr\Log\LoggerInterface;
use RuntimeException;

readonly class InMemoryTransport {

	public function __construct( private Server $server, private LoggerInterface $logger ) {
	}

	/**
	 * @return array{0: MemoryStream, 1: MemoryStream}
	 */
	public function connect(): array {
		$shared_stream = new class($this->server,$this->logger) extends MemoryStream {
			private LoggerInterface $logger;

			public function __construct( private readonly Server $server, LoggerInterface $logger ) {
				$this->logger = $logger;
			}

			/**
			 * Send a JsonRpcMessage or Exception to the server via SSE.
			 *
			 * @param JsonRpcMessage|Exception $message The JSON-RPC message or exception to send.
			 *
			 * @return void
			 *
			 * @throws InvalidArgumentException If the message is not a JsonRpcMessage.
			 * @throws RuntimeException If sending the message fails.
			 */
			public function send( mixed $message ): void {
				if ( ! $message instanceof JsonRpcMessage ) {
					throw new InvalidArgumentException( 'Only JsonRpcMessage instances can be sent.' );
				}

				$response = $this->server->handle_message( $message );

				$this->logger->debug( 'Received response for sent message: ' . json_encode( $response, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT ) );

				if ( null !== $response ) {
					parent::send( $response );
				}
			}
		};

		return [ $shared_stream, $shared_stream ];
	}
}
