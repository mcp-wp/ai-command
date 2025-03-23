<?php

namespace WP_CLI\AiCommand\MCP;

use InvalidArgumentException;
use Mcp\Server\NotificationOptions;
use Mcp\Server\Server as McpServer;
use Mcp\Shared\ErrorData;
use Mcp\Shared\McpError;
use Mcp\Shared\Version;
use Mcp\Types\CallToolResult;
use Mcp\Types\Implementation;
use Mcp\Types\InitializeResult;
use Mcp\Types\JSONRPCError;
use Mcp\Types\JsonRpcErrorObject;
use Mcp\Types\JsonRpcMessage;
use Mcp\Types\JSONRPCNotification;
use Mcp\Types\JSONRPCRequest;
use Mcp\Types\JSONRPCResponse;
use Mcp\Types\ListResourcesResult;
use Mcp\Types\ListResourceTemplatesResult;
use Mcp\Types\ListToolsResult;
use Mcp\Types\ReadResourceResult;
use Mcp\Types\RequestId;
use Mcp\Types\Resource;
use Mcp\Types\ResourceTemplate;
use Mcp\Types\Result;
use Mcp\Types\TextContent;
use Mcp\Types\TextResourceContents;
use Mcp\Types\Tool;
use Mcp\Types\ToolInputSchema;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class Server {
	private array $data = [];

	/**
	 * @var array<string, Tool>
	 */
	private array $tools = [];

	/**
	 * @var Array<Resource>
	 */
	private array $resources = [];

	/**
	 * @var Array<ResourceTemplate>
	 */
	private array $resource_templates = [];

	protected McpServer $mcp_server;

	protected LoggerInterface $logger;

	public function __construct( private readonly string $name, ?LoggerInterface $logger = null ) {
		$this->logger = $logger ?? new NullLogger();

		$this->mcp_server = new McpServer( $name, $this->logger );

		$this->mcp_server->registerHandler(
			'initialize',
			[ $this, 'initialize' ]
		);

		$this->mcp_server->registerHandler(
			'tools/list',
			[ $this, 'list_tools' ]
		);

		$this->mcp_server->registerHandler(
			'tools/call',
			[ $this, 'call_tool' ]
		);

		$this->mcp_server->registerHandler(
			'resources/list',
			[ $this, 'list_resources' ]
		);

		$this->mcp_server->registerHandler(
			'resources/read',
			[ $this, 'read_resources' ]
		);

		$this->mcp_server->registerHandler(
			'resources/templates/list',
			[ $this, 'list_resource_templates' ]
		);
	}

	public function register_tool( array $tool_definition ): void {
		if ( ! isset( $tool_definition['name'] ) || ! is_callable( $tool_definition['callable'] ) ) {
			throw new InvalidArgumentException( "Invalid tool definition. Must be an array with 'name' and 'callable'." );
		}

		$name         = $tool_definition['name'];
		$callable     = $tool_definition['callable'];
		$description  = $tool_definition['description'] ?? null;
		$input_schema = $tool_definition['inputSchema'] ?? null;

		$this->tools[ $name ] = [
			'tool'        => new Tool(
				$name,
				ToolInputSchema::fromArray(
					$input_schema,
				),
				$description
			),
			'callable'    => $callable,
			'inputSchema' => $input_schema,
		];
	}

	public function initialize(): InitializeResult {
		return new InitializeResult(
			capabilities: $this->mcp_server->getCapabilities( new NotificationOptions(), [] ),
			serverInfo: new Implementation(
				$this->name,
				'0.0.1', // TODO: Make dynamic.
			),
			protocolVersion: Version::LATEST_PROTOCOL_VERSION
		);
	}

	// TODO: Implement pagination, see https://spec.modelcontextprotocol.io/specification/2024-11-05/server/utilities/pagination/#response-format
	// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found
	public function list_tools( $params ): ListToolsResult {
		$prepared_tools = [];
		foreach ( $this->tools as $tool ) {
			$prepared_tools[] = $tool['tool'];
		}

		return new ListToolsResult( $prepared_tools );
	}

	public function call_tool( $params ): CallToolResult {
		$found_tool = null;
		foreach ( $this->tools as $name => $tool ) {
			if ( $name === $params->name ) {
				$found_tool = $tool;
				break;
			}
		}

		if ( ! $found_tool ) {
			throw new InvalidArgumentException( "Unknown tool: {$params->name}" );
		}

		$result = call_user_func( $found_tool['callable'], $params->arguments );

		if ( $result instanceof CallToolResult ) {
			return $result;
		}

		if ( is_wp_error( $result ) ) {
			return new CallToolResult(
				[
					new TextContent(
						$result->get_error_message()
					),
				],
				true
			);
		}

		if ( is_string( $result ) ) {
			$result = [ new TextContent( $result ) ];
		}

		if ( ! is_array( $result ) ) {
			$result = [ $result ];
		}
		return new CallToolResult( $result );
	}

	// TODO: Implement pagination, see https://spec.modelcontextprotocol.io/specification/2024-11-05/server/utilities/pagination/#response-format
	// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found
	public function list_resources(): ListResourcesResult {
		return new ListResourcesResult( $this->resources );
	}

	// TODO: Make dynamic.
	public function read_resources( $params ): ReadResourceResult {
		$uri = $params->uri;
		if ( 'example://greeting' !== $uri ) {
			throw new InvalidArgumentException( "Unknown resource: {$uri}" );
		}

		return new ReadResourceResult(
			[
				new TextResourceContents(
					'Hello from the example MCP server!',
					$uri,
					'text/plain'
				),
			]
		);
	}

	public function register_resource( Resource $res ): void {
		$this->resources[ $res->name ] = $res;
	}

	// TODO: Implement pagination, see https://spec.modelcontextprotocol.io/specification/2024-11-05/server/utilities/pagination/#response-format
    // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found
	public function list_resource_templates( $params ): ListResourceTemplatesResult {
		return new ListResourceTemplatesResult( $this->resource_templates );
	}

	public function register_resource_template( ResourceTemplate $resource_template ): void {
		$this->resource_templates[ $resource_template->name ] = $resource_template;
	}

	/**
	 * Processes an incoming message from the client.
	 */
	public function handle_message( JsonRpcMessage $message ) {
		$this->logger->debug( 'Received message: ' . json_encode( $message ) );

		$inner_message = $message->message;

		try {
			if ( $inner_message instanceof JSONRPCRequest ) {
				// It's a request
				return $this->process_request( $inner_message );
			}

			if ( $inner_message instanceof JSONRPCNotification ) {
				// It's a notification
				$this->process_notification( $inner_message );
				return null;
			}

			// Server does not expect responses from client; ignore or log
			$this->logger->warning( 'Received unexpected message type: ' . get_class( $inner_message ) );
		} catch ( McpError $e ) {
			if ( $inner_message instanceof JSONRPCRequest ) {
				return $this->send_error( $inner_message->id, $e->error );
			}
		} catch ( \Exception $e ) {
			$this->logger->error( 'Error handling message: ' . $e->getMessage() );
			if ( $inner_message instanceof JSONRPCRequest ) {
				// Code -32603 is Internal error as per JSON-RPC spec
				return $this->send_error(
					$inner_message->id,
					new ErrorData(
						-32603,
						$e->getMessage()
					)
				);
			}
		}
	}

	/**
	 * Processes a JSONRPCRequest message.
	 */
	private function process_request( JSONRPCRequest $request ): JsonRpcMessage {
		$method   = $request->method;
		$handlers = $this->mcp_server->getHandlers();
		$handler  = $handlers[ $method ] ?? null;

		if ( null === $handler ) {
			throw new McpError(
				new ErrorData(
					-32601, // Method not found
					"Method not found: {$method}"
				)
			);
		}

		$params = $request->params ?? null;
		$result = $handler( $params );

		if ( ! $result instanceof Result ) {
			$result = new Result();
		}

		return $this->send_response( $request->id, $result );
	}

	/**
	 * Processes a JSONRPCNotification message.
	 */
	private function process_notification( JSONRPCNotification $notification ): void {
		$method   = $notification->method;
		$handlers = $this->mcp_server->getNotificationHandlers();
		$handler  = $handlers[ $method ] ?? null;

		if ( null !== $handler ) {
			$params = $notification->params ?? null;
			$handler( $params );
		}

		$this->logger->warning( "No handler registered for notification method: $method" );
	}

	/**
	 * Sends a response to a request.
	 *
	 * @param RequestId $id The request ID to respond to.
	 * @param Result $result The result object.
	 */
	private function send_response( RequestId $id, Result $result ): JsonRpcMessage {
		// Create a JSONRPCResponse object and wrap in JsonRpcMessage
		$response = new JSONRPCResponse(
			'2.0',
			$id,
			$result
		);
		$response->validate();

		return new JsonRpcMessage( $response );
	}


	/**
	 * Sends an error response to a request.
	 *
	 * @param RequestId $id The request ID to respond to.
	 * @param ErrorData $error The error data.
	 */
	private function send_error( RequestId $id, ErrorData $error ): JsonRpcMessage {
		$error_object = new JsonRpcErrorObject(
			$error->code,
			$error->message,
			$error->data ?? null
		);

		$response = new JSONRPCError(
			'2.0',
			$id,
			$error_object
		);
		$response->validate();

		return new JsonRpcMessage( $response );
	}
}
