# WP-CLI AI Command

A WP-CLI package that enables AI interactions with WordPress via the Model Context Protocol (MCP).

## Project Overview

- **Type**: WP-CLI package
- **PHP Version**: 8.2+
- **Namespace**: `McpWp\AiCommand`
- **License**: Apache-2.0

## Architecture

```
src/
├── AI/              # AI client implementations (WpAiClient, AiClient)
├── MCP/             # MCP protocol implementation
│   ├── Servers/     # MCP servers (WP_CLI tools)
│   └── Client.php   # MCP client
├── Utils/           # Utilities (logging, config)
├── AiCommand.php    # Main `wp ai` command
├── CredentialsCommand.php  # `wp ai credentials` subcommand
├── McpCommand.php   # `wp mcp` command
└── McpServerCommand.php    # `wp mcp server` subcommand
```

## Development Commands

```bash
# Run all tests
composer test

# Individual test suites
composer phpunit      # PHPUnit tests
composer behat        # Behat integration tests
composer phpcs        # Code style checks
composer phpstan      # Static analysis
composer lint         # Linter

# Fix code style
composer format       # or: composer phpcbf

# Prepare test environment
composer prepare-tests
```

## Code Style

- Uses WP_CLI_CS ruleset (WordPress coding standards)
- Global namespace prefix: `McpWp\AiCommand` (classes) or `ai_command` (functions/variables)
- Run `composer format` to auto-fix style issues

## Key Dependencies

- `logiscape/mcp-sdk-php`: MCP SDK for PHP
- `mcp-wp/mcp-server`: MCP server implementation
- `wp-cli/wp-cli`: WP-CLI framework
- `wordpress/wp-ai-client`: WordPress AI client (dev dependency for testing)

## WP-CLI Commands

- `wp ai` - Main AI interaction command
- `wp ai credentials list|set|delete` - Manage AI provider API keys
- `wp mcp prompt` - MCP prompt handling
- `wp mcp server add|list|remove|update` - Manage MCP servers

## Testing Notes

- PHPUnit tests are in `tests/phpunit/`
- Behat feature tests are in `features/`
- PHPStan config: `phpstan.neon.dist`
