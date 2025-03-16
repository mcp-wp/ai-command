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
| `Server::list_resources()`  | List registered resources. |
| `Server::read_resource()`  | Retrieve resource data. Uses `get_resource_data()` method. |
| `Server::get_resource_data()`  | Retrieve resource data. |
| `Server::validate_input()`  | Input validation for AI tool calls. |
| `Server::handle_get_request()`  | Retrive stored resource data. |
| `Server::create_success_response()`  | Generates JSON-RPC success response. |
| `Server::create_error_response()`  | Generates JSON-RPC error response. |
| `Server::process_request()`  | Wrapper for `handle_request()` method. |


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
$server->register_resource(
	[
		'name'        => 'product_catalog',
		'uri'         => 'file://./products.json',
		'description' => 'Product catalog',
		'mimeType'    => 'application/json',
		'filePath'    => './products.json'
	]
);
```

List resources

```PHP
$server    = new WP_CLI\AiCommand\MCP\Server();
$resources = $server->list_resources();

echo json_encode( $resources, JSON_PRETTY_PRINT );
```

Read resource

```PHP
$server        = new WP_CLI\AiCommand\MCP\Server();
$resource_data = $server->read_resource( 'file://./products.json' );

echo json_encode( $resource_data, JSON_PRETTY_PRINT );
```

Validate input

```PHP
$input  = [ 'price' => 100, 'quantity' => 2 ];
$schema = $server->get_capabilities()['methods'][0]['inputSchema'];

$result = $server->validate_input( $input, $schema );
```

## `RouteInformation` class

The `RouteInformation` class encapsulates details about a WordPress REST API route, including its method type (`GET`, `POST`, `PUT`, etc.), callback function, and whether it conforms to a `WP_REST_Controller`. The class provides helper methods for route sanitization, method checking, and controller validation.

### Methods

| Name | Return Type | Description |
| --- | --- | --- |
| `RouteInformation::get_sanitized_route_name()` | `string` | Returns a cleaned-up route name (e.g., GET_wp-v2-posts_p_id). |
| `RouteInformation::get_method()` | `string` | Returns the HTTP method (`GET`, `POST`, etc.). |
| `RouteInformation::is_create()` | `bool` | Returns `true` if the method is `POST`. |
| `RouteInformation::is_update()` | `bool` | Returns `true` if the method is `PUT` or `PATCH`. |
| `RouteInformation::is_delete()` | `bool` | Returns `true` if the method is `DELETE`. |
| `RouteInformation::is_get()` | `bool` | Returns `true` if the method is `GET`. |
| `RouteInformation::is_singular()` | `bool` | Returns `true` if the route targets a single resource. |
| `RouteInformation::is_list()` | `bool` | Returns `true` if the route retrieves multiple resources. |
| `RouteInformation::is_wp_rest_controller()` | `bool` | Returns `true` if the callback is a valid REST controller. |
| `RouteInformation::get_wp_rest_controller()` | `WP_REST_Controller` | Returns the controller instance (throws an error if invalid). |

## `MapRESTtoMCP` class

The `MapRESTtoMCP` class is responsible for mapping WordPress REST API endpoints into MCP tools. It dynamically registers REST API routes as AI-callable tools in the MCP system.

This class enables AI-driven automation by exposing WordPress REST API endpoints to AI services in WP-CLI.

### Methods

| Name | Return Type | Description |
| --- | --- | --- |
| `MapRESTtoMCP::args_to_schema()` | `array` | Converts REST API arguments into JSON Schema. |
| `MapRESTtoMCP::sanitize_type()` | `string` | Maps REST API types to standardized types. |
| `MapRESTtoMCP::map_rest_to_mcp()` | `void` | Registers REST API endpoints as AI tools in MCP. |
| `MapRESTtoMCP::generate_description()` | `string` | Creates human-readable descriptions for API tools. |
| `MapRESTtoMCP::rest_callable()` | `array` | Executes REST API calls and returns formatted data. |
