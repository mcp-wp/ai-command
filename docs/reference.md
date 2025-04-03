# Reference

## Table of Contents

- [`AiCommand` class](#aicommand-class)
- [`Client` class](#client-class)
- [`Server` class](#server-class)
- [`RouteInformation` class](#routeinformation-class)
- [`MapRESTtoMCP` class](#mapresttomcp-class)
- [`MediaManager` class](#mediamanager-class)
- [`ImageTools` class](#imagetools-class)


## `AiCommand` class

The `AiCommand` class registers CLI command for WP-CLI. It is a WP-CLI command handler designed to integrate AI capabilities within WordPress. It connects AI-driven services with WP-CLI using the MCP (Multi-Client Processor) architecture, allowing AI models to:

- Access resources (e.g., WordPress posts, users, products).
- Call AI-powered [tools](tools.md) (e.g., image generation, event lookup).
- Execute AI-generated functions dynamically.

This class follows an MCP client-server architecture, where:

- Hosts (LLM applications like ChatGPT or IDEs) initiate connections.
- [Clients](#client-class) manage direct communication with the server inside a host.
- [Servers](#server-class) provide tools, resources, and context to the AI model.

### Methods

| Name | Return Type | Description |
| --- | --- | --- |
| `AiCommand::__invoke()` | `void` | Executes AI-driven WP-CLI commands. |
| `AiCommand::register_tools( $server )` | `void` | Registers AI-powered tools in MCP. See [available tools](tools.md). |
| `AiCommand::register_resources( $server )` | `void` | Registers data resources (e.g., users, products). |

## `Client` class

The `Client` class acts as the AI service interface for the MCP (Multi-Client Processor) system within WP-CLI. It communicates with the `Server` class via JSON-RPC, enabling AI-powered text generation, function calls, and image generation.

This class supports:

- AI service interaction (via `call_ai_service()`).
- Function execution and AI response handling.
- Dynamic REST resource access (`list_resources()`, `read_resource()`).
- Image generation via AI models (`get_image_from_ai_service()`).

### Properties

| Name | Visibility modifier | Description |
| ---  | --- | --- |
| `$server`  | private | An instance of MCPServer. |

### Methods

| Name | Return Type | Description |
| --- | --- | --- |
| `Client::__construct()`  | | Constructor |
| `Client::send_request()` | `array` | Sends JSON-RPC requests to the MCP server. |
| `Client::__call()` | `mixed` | Dynamically forwards method calls to `send_request()`. |
| `Client::list_resources()` | `array` | Retrieves all registered resources from MCP. |
| `Client::read_resource( $uri )` | `array` | Reads and returns data from a specified resource. |
| `Client::get_image_from_ai_service( $prompt )` | `string` | Generates an AI image from a prompt and returns the file path. Uses `AI_Capability::IMAGE_GENERATION` capibilities. |
| `Client::call_ai_service_with_prompt( $prompt )` | `string` | Calls the AI service with a prompt for text generation. |
| `Client::call_ai_service( $contents )` | `mixed` | Handles AI-generated responses, including text and function calls. |

## `Server` class

The Server class is a core component of MCP (Multi-Client Processor) within WP-CLI. It acts as a JSON-RPC 2.0 server, handling data requests, tool registrations, and AI-driven function execution.

This class provides:

- Tool registration (`register_tool()`)
- Resource registration (`register_resource()`)
- JSON-RPC request handling (`handle_request()`)
- AI service capabilities retrieval (`get_capabilities()`)

### Properties

| Name | Visibility modifier | Description |
| ---  | --- | --- |
| `$data`  | private | Stores structured data (e.g., users, products). |
| `$tools`  | private | Registered AI-callable tools (functions AI can invoke). |
| `$resources`  | private | Registered data resources accessible to AI. |

### Methods

| Name | Return Type | Description |
| --- | --- | --- |
| `Server::__construct()` | `void` | Initializes the MCP Server and loads default data (users, products). |
| `Server::register_tool( array $tool_definition )` | `void` | Registers an AI tool with a name, description, input schema, and a callable function. |
| `Server::register_resource( array $resource_definition )` | `void` | Registers a data resource with a name, URI, and optional description. |
| `Server::get_capabilities()` | `array` | Returns the list of registered AI tools and resources available in MCP. |
| `Server::handle_request( string $request_data )` | `false\|string` | Parses JSON-RPC 2.0 requests. Validates structure and executes method calls. |
| `Server::list_resources()` | `array` | Returns all registered resources, including name, URI, and description. |
| `Server::read_resource( string $uri )` | `array` | Retrieves a specific resource by its URI, returning structured data. |
| `Server::get_resource_data( $mcp_resource )` | `mixed` | Fetches resource data from a file or the internal dataset. |
| `Server::validate_input( $input, $schema )` | `array` | Validates tool input against the JSON schema, checking required fields and types. |
| `Server::handle_get_request( $path, $params )` | `array` | Handles get_ requests for accessing resources like `get_users` or `get_products`. |
| `Server::create_success_response( $id, $result )` | `false\|string` | Generates JSON-RPC success response. |
| `Server::create_error_response( $id, $message, $code )` | `false\|string` | Generates JSON-RPC error response. |
| `Server::process_request( $request_data )` | `false\|string` | Wrapper for `handle_request()` method. |

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
$server    = new McpWp\AiCommand\MCP\Server();
$resources = $server->list_resources();

echo json_encode( $resources, JSON_PRETTY_PRINT );
```

Read resource

```PHP
$server        = new McpWp\AiCommand\MCP\Server();
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

The `RouteInformation` class encapsulates metadata about a WordPress REST API route. It provides methods to determine route characteristics, REST method type, and controller details.

This class is used to:

- Identify REST route types (`singular`/`list`, `GET`/`POST`/`DELETE`, etc.).
- Validate and process REST controller callbacks.
- Generate sanitized route names for MCP registration.

### Methods

| Name | Return Type | Description |
| --- | --- | --- |
| `RouteInformation::__construct($route, $method, $callback)` | `void` | Initializes the class with a REST route, method, and callback. |
| `RouteInformation::get_sanitized_route_name()` | `string` | Returns a clean, MCP-compatible route name. |
| `RouteInformation::get_method()` | `string` | Retrieves the HTTP method (GET, POST, etc.). |
| `RouteInformation::is_create()` | `bool` | Checks if the method is POST (Create). |
| `RouteInformation::is_update()` | `bool` | Checks if the method is PUT or PATCH (Update). |
| `RouteInformation::is_delete()` | `bool` | Checks if the method is DELETE (Delete). |
| `RouteInformation::is_get()` | `bool` | Checks if the method is GET (Retrieve). |
| `RouteInformation::is_singular()` | `bool` | Determines if the route targets a single resource. |
| `RouteInformation::is_list()` | `bool` | Determines if the route retrieves a list. |
| `RouteInformation::get_scope()` | `string` | Returns the scope (post, user, taxonomy, or default). |
| `RouteInformation::is_wp_rest_controller()` | `bool` | Checks if the callback belongs to a WP REST controller. |
| `RouteInformation::get_wp_rest_controller()` | `WP_REST_Controller` | Retrieves the REST controller instance or throws an error. |

## `MapRESTtoMCP` class

The `MapRESTtoMCP` class is responsible for mapping WordPress REST API endpoints into Machine Contextual Processing (MCP) tools. It does this by:

- Extracting route details from the REST API.
- Generating input schemas from REST arguments.
- Creating AI tools dynamically based on route metadata.

This enables seamless AI-driven interactions with the WordPress REST API.

### Methods

| Name | Return Type | Description |
| --- | --- | --- |
| `MapRESTtoMCP::args_to_schema( $args )` | `array` | Converts REST API arguments into a structured schema. |
| `MapRESTtoMCP::sanitize_type( $type )` | `string` | Maps input types to standard schema types. |
| `MapRESTtoMCP::map_rest_to_mcp()` | `array` | Registers REST API endpoints as AI tools in MCP. |
| `MapRESTtoMCP::generate_description( $info )` | `string` | Creates human-readable descriptions for API routes. |
| `MapRESTtoMCP::rest_callable( $inputs, $route, $method_name, $server )` | `array` | Executes a REST API call dynamically. |

## `MediaManager` class

The `MediaManager` class provides a static method to upload a media file to the WordPress Media Library. It:

- Copies a file into the WordPress uploads directory.
- Registers the file as a WordPress media attachment.
- Generates and updates attachment metadata.

This class is useful for automated media uploads within WP-CLI or AI-powered workflows.

### Methods

| Name | Return Type | Description |
| --- | --- | --- |
| `MediaManager::upload_to_media_library( $media_path )` | `int` | Uploads a media file to WordPress and returns its attachment ID. |

## `ImageTools` class

The `ImageTools` class provides AI-powered image generation functionality within WP-CLI.
It:

- Integrates AI-based image generation tools.
- Registers the tool in the system for easy access.
- Uses a client to fetch AI-generated images.

This class is used to dynamically generate images based on user prompts.

### Methods

| Name | Return Type | Description |
| --- | --- | --- |
| `ImageTools::__construct( $client )` | `void` | Initializes ImageTools with an AI client. |
| `ImageTools::get_tools()` | `array` | Returns a list of available AI tools. |
| `ImageTools::image_generation_tool()` | `Tool` | Creates an AI-powered image generation tool. |
