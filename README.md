# WP-CLI as an MCP Host

This repository is for the [CloudFest Hackathon 2025 project](https://hackathon.cloudfest.com/project/wp-cli-mcp-host/) to implement the [Model Context Protocol](https://modelcontextprotocol.io/) (MCP) in the WordPress ecosystem, specifically integrating it with WP-CLI.

The core innovation is transforming WordPress into an MCP Server and WP-CLI into an MCP Host through a new package, enabling direct AI interactions with WordPress installations during development. This approach provides developers with powerful AI capabilities without requiring a live site or REST API endpoints.

**WordPress MCP Server Layer:**

1. Implementation of MCP Server interfaces in WordPress
2. Resource providers for posts, pages, media, and other WordPress content types
3. Tool definitions for common WordPress actions (content creation, media handling)
4. Context providers for WordPress configuration and site state

**WP-CLI MCP Host Package:**

1. MCP Host implementation within WP-CLI framework
2. New command namespace for AI operations
3. Integration with (local and remote) LLM providers
4. Transport layer for local WordPress communication

You can think of MCP as the "USB port for LLMs", a standard way for LLMs to interact with any third-party system using things like function calling.

While the Hackathon project focuses on WP-CLI, the _MCP Server_ is usage-agnostic. It could also be exposed via HTTP or so in the future.

The _MCP Host_, gets information (such as list of available tools) from the server and passes it on to the LLM (e.g. Gemini).

## Installing

Installing this package requires WP-CLI v2.11 or greater. Update to the latest stable release with `wp cli update`.

Tip: for better on support of the latest PHP versions, use the v2.12 nightly build with `wp cli update --nightly`.

To install the latest development version of this package, use the following command instead:

```bash
wp package install swissspidy/ai-command:dev-main
```

Right now, the plugin requires a WordPress site with the [AI Services plugin](https://wordpress.org/plugins/ai-services) installed. 


## Using

This package implements the following commands:

### wp ai

AI prompt.

~~~
wp ai <prompt> [--skip-wordpress]
~~~

**OPTIONS**

	<prompt>
		AI prompt.

	[--skip-wordpress]
		Run command without loading WordPress. (Not implemented yet)

**EXAMPLES**

    # Get data from WordPress
    $ wp ai "What are the titles of my last three posts?"
    - Hello world
    - My awesome post
    - Another post

    # Interact with multiple MCP servers.
    $ wp ai "Take file foo.txt and create a new blog post from it"
    Success: Blog post created.



### wp mcp server list

Lists available MCP servers.

~~~
wp mcp server list [--format=<format>]
~~~

**OPTIONS**

	[--format=<format>]
		Render output in a particular format.
		---
		default: table
		options:
		  - table
		  - csv
		  - json
		  - count
		---

**EXAMPLES**

    # Greet the world.
    $ wp mcp server list
    Success: Hello World!

    # Greet the world.
    $ wp ai "create 10 test posts about swiss recipes and include generated featured images"
    Success: Hello World!



### wp mcp server add

Add a new MCP server to the list

~~~
wp mcp server add <name> <server>
~~~

**OPTIONS**

	<name>
		Name for referencing the server later

	<server>
		Server command or URL.

**EXAMPLES**

    # Add server from URL.
    $ wp mcp server add "server-github" "https://github.com/mcp"
    Success: Server added.

    # Add server with command to execute
    $ wp mcp server add "server-filesystem" "npx -y @modelcontextprotocol/server-filesystem /my/allowed/folder/"
    Success: Server added.



### wp mcp server remove

Remove a new MCP server from the list

~~~
wp mcp server remove <name>
~~~

**OPTIONS**

	<name>
		Name of the server to remove

**EXAMPLES**

    # Remove server.
    $ wp mcp server remove "server-filesystem"
    Success: Server removed.

## Contributing

We appreciate you taking the initiative to contribute to this project.

Contributing isn’t limited to just code. We encourage you to contribute in the way that best fits your abilities, by writing tutorials, giving a demo at your local meetup, helping other users with their support questions, or revising our documentation.

For a more thorough introduction, [check out WP-CLI's guide to contributing](https://make.wordpress.org/cli/handbook/contributing/). This package follows those policy and guidelines.

### Reporting a bug

Think you’ve found a bug? We’d love for you to help us get it fixed.

Before you create a new issue, you should [search existing issues](https://github.com/swissspidy/ai-command/issues?q=label%3Abug%20) to see if there’s an existing resolution to it, or if it’s already been fixed in a newer version.

Once you’ve done a bit of searching and discovered there isn’t an open or fixed issue for your bug, please [create a new issue](https://github.com/swissspidy/ai-command/issues/new). Include as much detail as you can, and clear steps to reproduce if possible. For more guidance, [review our bug report documentation](https://make.wordpress.org/cli/handbook/bug-reports/).

### Creating a pull request

Want to contribute a new feature? Please first [open a new issue](https://github.com/swissspidy/ai-command/issues/new) to discuss whether the feature is a good fit for the project.

Once you've decided to commit the time to seeing your pull request through, [please follow our guidelines for creating a pull request](https://make.wordpress.org/cli/handbook/pull-requests/) to make sure it's a pleasant experience. See "[Setting up](https://make.wordpress.org/cli/handbook/pull-requests/#setting-up)" for details specific to working on this package locally.

### Cloudfest Hackathon Contributors

- Pascal Birchler - [@swissspidy](https://github.com/swissspidy)
- Jan-Willem Oostendorp - [@janw-me](https://github.com/janw-me)
- Joost de Valk - [@jdevalk](https://github.com/jdevalk)
- Marco Chiesi - [@marcochiesi](https://github.com/marcochiesi)
- Matt Biscay - [@skyminds](https://github.com/skyminds)
- Moritz Bappert - [@moritzbappert](https://github.com/moritzbappert)
- James Hunt - [@thetwopct](https://github.com/thetwopct)
- Tome Pajkovski - [@tomepajk](https://github.com/tomepajk)
- David Mosterd - [@davidmosterd](https://github.com/davidmosterd)
- Milana Cap - [@zzap](https://github.com/zzap)

## Support

GitHub issues aren't for general support questions, but there are other venues you can try: https://wp-cli.org/#support
