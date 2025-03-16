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
| `AiCommand:__invoke( array $args, array $assoc_args )`  | <ul><li>Creates an AI server and client instance.</li><li>Registers tools & resources for AI processing.</li><li>Sends user input ($args[0]) to AI.</li><li>Outputs AI-generated results.</li></ul> |
| `AiCommand:register_tools( Server $server, $client )`  | Registers functionality AI can invoke. See [available tools](tools.md). |
| `AiCommand:register_resources( Server $server )`  | Registers structured datasets AI can access. See [available resources](). |


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
| `Client:__construct( Server $server )`  | Constructor |
| `Client:send_request( string $method, array $params = [] )`  | Constructs a JSON-RPC request, sends it to the AI service, and decodes the response. |
| `Client:__call( string $name, array $arguments )`  | A magic method to call any AI service method dynamically. |
| `Client:list_resources()`  | Retrieves available AI-generated content. |
| `Client:read_resource( string $uri )`  | Reads a specific AI-generated resource. |

## `Server` class
