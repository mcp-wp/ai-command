<?php

namespace McpWp\AiCommand\MCP;

use InvalidArgumentException;
use Mcp\Client\ClientSession;
use Mcp\Server\InitializationOptions;
use Mcp\Server\ServerSession;
use Mcp\Server\Transport\Transport;
use Mcp\Shared\RequestResponder;
use Mcp\Types\CallToolRequest;
use Mcp\Types\CallToolResult;
use Mcp\Types\ClientRequest;
use Mcp\Types\CompleteRequest;
use Mcp\Types\CompleteResult;
use Mcp\Types\EmptyResult;
use Mcp\Types\GetPromptRequest;
use Mcp\Types\GetPromptResult;
use Mcp\Types\InitializeRequest;
use Mcp\Types\InitializeResult;
use Mcp\Types\ListPromptsRequest;
use Mcp\Types\ListPromptsResult;
use Mcp\Types\ListResourcesRequest;
use Mcp\Types\ListResourcesResult;
use Mcp\Types\ListToolsRequest;
use Mcp\Types\ListToolsResult;
use Mcp\Types\PingRequest;
use Mcp\Types\ReadResourceRequest;
use Mcp\Types\ReadResourceResult;
use Mcp\Types\SubscribeRequest;
use Mcp\Types\UnsubscribeRequest;
use Psr\Log\LoggerInterface;


/**
 * ProxySession to pass messages from STDIO to an HTTP MCP server.
 */
class ProxySession extends ServerSession {
	protected ?ClientSession $client_session;

	public function __construct(
		protected readonly string $url,
		Transport $transport,
		InitializationOptions $init_options,
		?LoggerInterface $logger = null
	) {
		parent::__construct(
			$transport,
			$init_options,
			$logger
		);
	}

	/**
	 * Handle incoming requests. If it's the initialize request, handle it specially.
	 * Otherwise, ensure initialization is complete before handling other requests.
	 *
	 * @param ClientRequest $request The incoming client request.
	 * @param callable $respond The responder callable.
	 */
	public function handleRequest( RequestResponder $responder ): void {
		$request        = $responder->getRequest();
		$actual_request = $request->getRequest();
		$method         = $actual_request->method;

		$this->logger->info( 'Proxying request: ' . json_encode( $actual_request ) );

		$result = null;

		if ( ! isset( $this->client_session ) ) {
			$client               = new Client( $this->logger );
			$this->client_session = $client->connect(
				$this->url
			);
		}

		switch ( get_class( $actual_request ) ) {
			case InitializeRequest::class:
				$result = $this->client_session->sendRequest( $actual_request, InitializeResult::class );
				break;
			case SubscribeRequest::class:
			case UnsubscribeRequest::class:
			case PingRequest::class:
				$result = $this->client_session->sendRequest( $actual_request, EmptyResult::class );
				break;
			case ListResourcesRequest::class:
				$result = $this->client_session->sendRequest( $actual_request, ListResourcesResult::class );
				break;
			case ListToolsRequest::class:
				$result = $this->client_session->sendRequest( $actual_request, ListToolsResult::class );
				break;
			case CallToolRequest::class:
				$result = $this->client_session->sendRequest( $actual_request, CallToolResult::class );
				break;
			case ReadResourceRequest::class:
				$result = $this->client_session->sendRequest( $actual_request, ReadResourceResult::class );
				break;
			case ListPromptsRequest::class:
				$result = $this->client_session->sendRequest( $actual_request, ListPromptsResult::class );
				break;
			case GetPromptRequest::class:
				$result = $this->client_session->sendRequest( $actual_request, GetPromptResult::class );
				break;
			case CompleteRequest::class:
				$result = $this->client_session->sendRequest( $actual_request, CompleteResult::class );
				break;
		}

		if ( null === $result ) {
			throw new InvalidArgumentException( "Unhandled proxied request for method: $method / " . get_class( $actual_request ) );
		}

		$responder->sendResponse( $result );
	}
}
