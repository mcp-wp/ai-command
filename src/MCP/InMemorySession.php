<?php

namespace WP_CLI\AiCommand\MCP;

use InvalidArgumentException;
use Mcp\Client\ClientSession;
use Mcp\Shared\ErrorData;
use Mcp\Shared\McpError;
use Mcp\Shared\MemoryStream;
use Mcp\Types\JSONRPCError;
use Mcp\Types\JsonRpcMessage;
use Mcp\Types\JSONRPCRequest;
use Mcp\Types\JSONRPCResponse;
use Mcp\Types\McpModel;
use Mcp\Types\RequestId;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class InMemorySession extends ClientSession {
	private ?MemoryStream $read_stream;

	private ?MemoryStream $write_stream;

	private LoggerInterface|NullLogger $logger;

	private int $request_id = 0;

	/**
	 * ClientSession constructor.
	 *
	 * @param MemoryStream    $read_stream   Stream to read incoming messages from.
	 * @param MemoryStream    $write_stream  Stream to write outgoing messages to.
	 * @param LoggerInterface|null $logger  PSR-3 compliant logger.
	 *
	 * @throws InvalidArgumentException If the provided streams are invalid.
	 */
	public function __construct(
		MemoryStream $read_stream,
		MemoryStream $write_stream,
		?LoggerInterface $logger = null
	) {
		$this->logger       = $logger ?? new NullLogger();
		$this->read_stream  = $read_stream;
		$this->write_stream = $write_stream;

		parent::__construct(
			$read_stream,
			$write_stream,
			null,
			$this->logger
		);
	}

	/**
	 * Sends a request and waits for a typed result. If an error response is received, throws an exception.
	 *
	 * @param McpModel $request A typed request object (e.g., InitializeRequest, PingRequest).
	 * @param string $result_type The fully-qualified class name of the expected result type (must implement McpModel). TODO: Implement.
	 * @return McpModel The validated result object.
	 * @throws McpError If an error response is received.
	 */
	public function sendRequest( McpModel $request, string $result_type ): McpModel {
		$this->validate_request_object( $request );

		$request_id_value = $this->request_id++;
		$request_id       = new RequestId( $request_id_value );

		// Convert the typed request into a JSON-RPC request message
		// Assuming $request has public properties: method, params
		$json_rpc_request = new JsonRpcMessage(
			new JSONRPCRequest(
				'2.0',
				$request_id,
				$request->params ?? null,
				$request->method
			)
		);

		// Send the request message
		$this->writeMessage( $json_rpc_request );

		$message = $this->readNextMessage();

		$inner_message = $message->message;

		if ( $inner_message instanceof JSONRPCError ) {
			// It's an error response
			// Convert JsonRpcErrorObject into ErrorData
			$error_data = new ErrorData(
				$inner_message->error->code,
				$inner_message->error->message,
				$inner_message->error->data
			);
			throw new McpError( $error_data );
		}

		if ( $inner_message instanceof JSONRPCResponse ) {
			return $inner_message->result;
		}

		// Invalid response
		throw new InvalidArgumentException( 'Invalid JSON-RPC response received' );
	}

	private function validate_request_object( McpModel $request ): void {
		// Check if request has a method property
		if ( ! property_exists( $request, 'method' ) || empty( $request->method ) ) {
			throw new InvalidArgumentException( 'Request must have a method' );
		}
	}

	/**
	 * Write a JsonRpcMessage to the write stream.
	 *
	 * @param JsonRpcMessage $message The JSON-RPC message to send.
	 *
	 * @throws RuntimeException If writing to the stream fails.
	 *
	 * @return void
	 */
	protected function writeMessage( JsonRpcMessage $message ): void {
		$this->logger->debug( 'Sending message to server: ' . json_encode( $message, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT ) );
		$this->write_stream->send( $message );
	}

	/**
	 * Read the next message from the read stream.
	 *
	 * @throws RuntimeException If an invalid message type is received.
	 *
	 * @return JsonRpcMessage The received JSON-RPC message.
	 */
	protected function readNextMessage(): JsonRpcMessage {
		return $this->read_stream->receive();
	}

	/**
	 * Start any additional message processing mechanisms if necessary.
	 *
	 * @return void
	 */
	protected function startMessageProcessing(): void {
		// Not used.
	}

	/**
	 * Stop any additional message processing mechanisms if necessary.
	 *
	 * @return void
	 */
	protected function stopMessageProcessing(): void {
		// Not used.
	}
}
