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
| `AiCommand:__invoke( $args, $assoc_args )`  | <ul><li>Creates an AI server and client instance.</li><li>Registers tools & resources for AI processing.</li><li>Sends user input ($args[0]) to AI.</li><li>Outputs AI-generated results.</li></ul> |
| `AiCommand:register_tools( $server, $client )`  | Registers functionality AI can invoke. See [available tools](tools.md). |
| `AiCommand:register_resources( $server )`  | Registers structured datasets AI can access. See [available resources](). |


## `Client` class

## `Server` class
