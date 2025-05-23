<?php

namespace McpWp\AiCommand\MCP;

use Exception;
use InvalidArgumentException;
use Mcp\Shared\MemoryStream;
use Mcp\Types\JsonRpcMessage;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Psr\Log\NullLogger;
use Mcp\Types\JSONRPCRequest;
use Mcp\Types\JSONRPCNotification;
use Mcp\Types\JSONRPCResponse;
use Mcp\Types\JSONRPCError;
use Mcp\Types\RequestId;
use Mcp\Types\JsonRpcErrorObject;
use WpOrg\Requests\Response;

/**
 * Class HttpTransport
 *
 * Handles streamable-HTTP  based communication with an MCP server.
 */
class HttpTransport {
	/** @var LoggerInterface */
	private LoggerInterface $logger;

	/**
	 * SseTransport constructor.
	 *
	 * @param string               $url     The HTTP endpoint URL.
	 * @param array<string, mixed> $options Requests options.
	 * @param LoggerInterface|null $logger  PSR-3 compliant logger.
	 *
	 * @throws InvalidArgumentException If the URL is empty.
	 */
	public function __construct(
		private readonly string $url,
		private readonly array $options = [],
		?LoggerInterface $logger = null,
	) {
		if ( empty( $url ) ) {
			throw new InvalidArgumentException( 'URL cannot be empty' );
		}
		$this->logger = $logger ?? new NullLogger();
	}

	/**
	 * @return array{0: MemoryStream, 1: MemoryStream}
	 */
	public function connect(): array {
		$shared_stream = new class($this->url,$this->options, $this->logger) extends MemoryStream {
			private LoggerInterface $logger;

			private ?string $session_id = null;

			/**
			 * @param string               $url     URL to connect to.
			 * @param array<string, mixed> $options Requests options.
			 * @param LoggerInterface      $logger  PSR-3 compliant logger.
			 */
			public function __construct( private readonly string $url, private readonly array $options, LoggerInterface $logger ) {
				$this->logger = $logger;
			}

			/**
			 * Send a JsonRpcMessage or Exception to the server via SSE.
			 *
			 * @param JsonRpcMessage|Exception $item The JSON-RPC message or exception to send.
			 *
			 * @return void
			 *
			 * @throws InvalidArgumentException If the message is not a JsonRpcMessage.
			 * @throws RuntimeException If sending the message fails.
			 */
			public function send( mixed $item ): void {
				if ( ! $item instanceof JsonRpcMessage ) {
					throw new InvalidArgumentException( 'Only JsonRpcMessage instances can be sent.' );
				}

				/**
				 * @var Response $response
				 */
				$response = \WP_CLI\Utils\http_request(
					'POST',
					$this->url,
					// Wrong PHPDoc in Requests?
					// @phpstan-ignore argument.type
					json_encode( $item, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES ),
					[
						'Content-Type'   => 'application/json',
						'Mcp-Session-Id' => $this->session_id,
					],
					$this->options
				);

				if ( isset( $response->headers['mcp-session-id'] ) && ! isset( $this->session_id ) ) {
					$this->session_id = $response->headers['mcp-session-id'];
				}

				if ( empty( $response->body ) ) {
					return;
				}

				$data = json_decode( $response->body, true, 512, JSON_THROW_ON_ERROR );

				$this->logger->debug( 'Received response for sent message: ' . json_encode( $data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT ) );

				$json_rpc_response = $this->instantiateJsonRpcMessage( $data );

				parent::send( $json_rpc_response );
			}

			/**
			 * Instantiate a JsonRpcMessage from decoded data.
			 *
			 * @param array<string, mixed> $data The decoded JSON data.
			 *
			 * @return JsonRpcMessage The instantiated JsonRpcMessage object.
			 *
			 * @throws InvalidArgumentException If the message structure is invalid.
			 */
			private function instantiateJsonRpcMessage( array $data ): JsonRpcMessage {
				if ( ! isset( $data['jsonrpc'] ) || '2.0' !== $data['jsonrpc'] ) {
					throw new InvalidArgumentException( 'Invalid JSON-RPC version.' );
				}

				if ( isset( $data['method'] ) ) {
					// It's a Request or Notification
					if ( isset( $data['id'] ) ) {
						// It's a Request
						return new JsonRpcMessage(
							new JSONRPCRequest(
								'2.0',
								new RequestId( $data['id'] ),
								$data['params'] ?? null,
								$data['method']
							)
						);
					}

					// It's a Notification
					return new JsonRpcMessage(
						new JSONRPCNotification(
							'2.0',
							$data['params'] ?? null,
							$data['method']
						)
					);
				}

				if ( isset( $data['result'] ) || isset( $data['error'] ) ) {
					// It's a Response or Error
					if ( isset( $data['error'] ) ) {
						// It's an Error
						$error_data = $data['error'];
						return new JsonRpcMessage(
							new JSONRPCError(
								'2.0',
								new RequestId( $data['id'] ?? 0 ),
								new JsonRpcErrorObject(
									$error_data['code'],
									$error_data['message'],
									$error_data['data'] ?? null
								)
							)
						);
					}

					// It's a Response
					return new JsonRpcMessage(
						new JSONRPCResponse(
							'2.0',
							new RequestId( $data['id'] ?? 0 ),
							$data['result']
						)
					);
				}

				throw new InvalidArgumentException( 'Invalid JSON-RPC message structure.' );
			}
		};

		return [ $shared_stream, $shared_stream ];
	}
}
