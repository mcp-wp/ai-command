# WP-CLI AI Command with MCP support

[![Commit activity](https://img.shields.io/github/commit-activity/m/mcp-wp/ai-command)](https://github.com/mcp-wp/ai-command/pulse/monthly)
[![Code Coverage](https://codecov.io/gh/mcp-wp/ai-command/branch/main/graph/badge.svg)](https://codecov.io/gh/mcp-wp/ai-command)
[![License](https://img.shields.io/github/license/mcp-wp/ai-command)](https://github.com/mcp-wp/ai-command/blob/main/LICENSE)

This WP-CLI command enables direct AI interactions with WordPress installations during development by implementing the [Model Context Protocol](https://modelcontextprotocol.io/) (MCP).
It not only provides its own MCP server for controlling WordPress sites, but also allows connecting to any other local or remote MCP server.

[![Read documentation](https://img.shields.io/badge/Read%20documentation-24282D?style=for-the-badge&logo=Files&logoColor=ffffff)](https://mcp-wp.github.io/)

## Installing

Installing this package requires WP-CLI v2.11 or greater. Update to the latest stable release with `wp cli update`.

**Tip:** for better support of the latest PHP versions, use the v2.12 nightly build with `wp cli update --nightly`.

To install the latest development version of this package, use the following command instead:

```bash
wp package install mcp-wp/ai-command:dev-main
```

This package uses the **WP AI Client** (available in WordPress 7.0+ or via the AI plugin) for AI functionality.

### Configuration

Configure credentials for AI providers:

```bash
# Configure credentials for AI providers
wp ai credentials set openai sk-proj-YOUR-API-KEY
wp ai credentials set anthropic sk-ant-YOUR-API-KEY
wp ai credentials set google YOUR-GOOGLE-API-KEY

# List configured credentials
wp ai credentials list
```

Credentials are stored in the WordPress database and can also be managed through the WordPress admin settings screen.

### Reporting a bug

Think you’ve found a bug? We’d love for you to help us get it fixed.

Before you create a new issue, you should [search existing issues](https://github.com/mcp-wp/ai-command/issues?q=label%3Abug%20) to see if there’s an existing resolution to it, or if it’s already been fixed in a newer version.

Once you’ve done a bit of searching and discovered there isn’t an open or fixed issue for your bug, please [create a new issue](https://github.com/mcp-wp/ai-command/issues/new). Include as much detail as you can, and clear steps to reproduce if possible. For more guidance, [review our bug report documentation](https://make.wordpress.org/cli/handbook/bug-reports/).

### Creating a pull request

Want to contribute a new feature? Please first [open a new issue](https://github.com/mcp-wp/ai-command/issues/new) to discuss whether the feature is a good fit for the project.

Once you've decided to commit the time to seeing your pull request through, [please follow our guidelines for creating a pull request](https://make.wordpress.org/cli/handbook/pull-requests/) to make sure it's a pleasant experience.
