# Reference

## `AiCommand` class

### Description

The `AiCommand` class extends `WP_CLI_Command` to integrate AI-powered automation into WordPress through WP-CLI. It enables users to interact with an AI model by:

- Executing AI-driven prompts.
- Registering [tools](tools.md) that can be invoked by AI (e.g., calculations, greetings, image generation).
- Registering resources accessible by AI (e.g., user lists, product catalogs).
- Fetching [WordPress community events](https://developer.wordpress.org/reference/classes/wp_community_events/) dynamically.

This class follows an MCP client-server architecture, where:

- Hosts (LLM applications like ChatGPT or IDEs) initiate connections.
- [Clients](#client-class) manage direct communication with the server inside a host.
- [Servers](#server-class) provide tools, resources, and context to the AI model.

### Methods

| Name | Description |
| ---  | --- |
| `AiCommand::__invoke()`  | <ul><li>Creates an AI server and client instance.</li><li>Registers tools & resources for AI processing.</li><li>Sends user input ($args[0]) to AI.</li><li>Outputs AI-generated results.</li></ul> |
| `AiCommand::register_tools()`  | Registers functionality AI can invoke. See [available tools](tools.md). |
| `AiCommand::register_resources()`  | Registers structured datasets AI can access. See [available resources](). |


## `Client` class

`Client` class is part of the `WP_CLI\AiCommand\MCP` namespace and serves as an intermediary between the WP-CLI and an AI-based service. It interacts with an MCP (Multi-Client Processor) AI service using JSON-RPC over a local server instance.

The service supports:

- Sending and receiving requests from the AI service.
- Retrieving resources from the AI service.
- Generating images via AI.
- Processing AI-generated text responses.
- Managing function calls and executing AI-invoked functions dynamically.

### Properties

| Name | Visibility modifier | Description |
| ---  | --- | --- |
| `$server`  | private | An instance of MCPServer. |

### Methods

| Name | Description |
| ---  | --- |
| `Client::__construct()`  | Constructor |
| `Client::send_request()`  | Constructs a JSON-RPC request, sends it to the AI service, and decodes the response. |
| `Client::__call()`  | A magic method to call any AI service method dynamically. |
| `Client::list_resources()`  | Retrieves available AI-generated content. |
| `Client::read_resource()`  | Reads a specific AI-generated resource. |
| `Client::get_image_from_ai_service()`  | Calls an AI image generation service. Uses `AI_Capability::IMAGE_GENERATION` capibilities. Returns the image URL. |
| `Client::call_ai_service_with_prompt()`  | Calls AI with a text prompt. |
| `Client::call_ai_service()`  | AI function calls and processing. |

## `Server` class

The Server class serves as the core backend for the AI Command's MCP (Multi-Client Processor) in WP-CLI. It provides:

- Tool Registration - Defines AI-callable functions (e.g., calculations, event fetching).
- Resource Management - Stores and retrieves structured data (e.g., users, products).
- JSON-RPC Request Handling - Processes incoming requests and returns AI-usable responses.
- Validation & Error Handling - Ensures correct data formats and secure execution.

This class acts as the server component in the MCP architecture, interfacing with AI clients to process requests. It follows a JSON-RPC 2.0 protocol, ensuring a standardized communication format.

### Properties

| Name | Visibility modifier | Description |
| ---  | --- | --- |
| `$data`  | private | Stores structured data (e.g., users, products). |
| `$tools`  | private | Registered AI-callable tools (functions AI can invoke). |
| `$resources`  | private | Registered data resources accessible to AI. |

### Methods

| Name | Description |
| ---  | --- |
| `Server::__construct()`  | Constructor. Initializes sample user and product data. These datasets are accessible via JSON-RPC requests. |
| `Server::register_tool()`  | Registers AI-callable functions (`tools`). Each tool must include identifier (`name`) and function to execute (`callable`); `description` and `inputSchema` are optional. |
| `Server::register_resource()`  | Registers structured data for AI access. |
| `Server::get_capabilities()`  | Retrives server capabilities and returns the list of available tools and resources. Used by AI clients to understand what functions and data are accessible. |
| `Server::handle_request()`  | Parses JSON-RPC 2.0 requests. Validates structure and executes method calls. |
| `Server::process_method_call()`  | Determines whether the request is for fetching capabilities (`get_capabilities`), accessing data (`get_users`, `get_products`), or executing a tool (`calculate_total`, `greet`, etc.) |
| `Server::handle_data_request()`  | Extracts requested resource and returns structured data. |
| `Server::execute_tool()`  | Calls registered AI tools and validates input against schema. |
| `Server::create_success_response()`  | Generates JSON-RPC success response. |
| `Server::create_error_response()`  | Generates JSON-RPC error response. |

### Examples

Register a Tool

```PHP
$server->register_tool(
	[
		'name'     => 'calculate_total',
		'callable' => function( $params ) {
			return $params['price'] * $params['quantity'];
		},
		'inputSchema' => [
			'properties' => [
				'price'    => [ 'type' => 'integer' ],
				'quantity' => [ 'type' => 'integer' ]
			],
		],
	]
);
```

Register a Resource

```PHP
$server->register_resource([
	'name'        => 'product_catalog',
	'uri'         => 'file://./products.json',
	'description' => 'Product catalog',
	'mimeType'    => 'application/json',
	'filePath'    => './products.json'
]);
```
