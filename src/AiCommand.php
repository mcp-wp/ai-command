<?php

namespace McpWp\AiCommand;

use McpWp\AiCommand\AI\WpAiClient;
use WP_CLI;
use WP_CLI\Utils;
use WP_CLI_Command;

/**
 * AI command class.
 *
 * Allows interacting with an LLM using the WP AI Client.
 */
class AiCommand extends WP_CLI_Command {

	/**
	 * AI prompt.
	 *
	 * ## OPTIONS
	 *
	 * <prompt>
	 * : AI prompt.
	 *
	 * [--skip-wordpress]
	 * : Run command without loading WordPress. (Not implemented yet)
	 *
	 * [--service=<service>]
	 * : Manually specify the AI service to use.
	 * Examples: 'google', 'anthropic', 'openai'.
	 *
	 * [--model=<model>]
	 * : Manually specify the LLM model that should be used.
	 * Examples: 'gemini-2.0-flash', 'gpt-4o', 'claude-sonnet-4-5'.
	 *
	 * ## EXAMPLES
	 *
	 *     # Ask a simple question
	 *     $ wp ai "Explain WordPress in one sentence"
	 *     WordPress is a free and open-source content management system...
	 *
	 *     # Use a specific model
	 *     $ wp ai "Summarize the history of WordPress" --model=gpt-4o
	 *     WordPress was created in 2003...
	 *
	 * @when before_wp_load
	 *
	 * @param string[] $args Indexed array of positional arguments.
	 * @param array<string, string> $assoc_args Associative arguments.
	 */
	public function __invoke( array $args, array $assoc_args ): void {
		$with_wordpress = null === Utils\get_flag_value( $assoc_args, 'skip-wordpress' );
		if ( $with_wordpress ) {
			WP_CLI::get_runner()->load_wordpress();
		} else {
			WP_CLI::error( 'Not implemented yet.' );
		}

		// Ensure WP AI Client is available.
		if ( ! class_exists( '\WordPress\AI_Client\AI_Client' ) ) {
			WP_CLI::error( 'This command requires the WP AI Client. Please ensure WordPress 7.0+ or the AI plugin is installed and activated.' );
		}

		$service = Utils\get_flag_value( $assoc_args, 'service' );
		$model   = Utils\get_flag_value( $assoc_args, 'model' );

		$ai_client = new WpAiClient( [], false, $service, $model );
		$ai_client->call_ai_service_with_prompt( $args[0] );
	}

	/**
	 * Returns a combined list of all tools for all existing MCP client sessions.
	 *
	 * @param array<ClientSession> $sessions List of available sessions.
	 * @return array List of tools.
	 *
	 * @phpstan-return ToolDefinition[]
	 */
	protected function get_tools( array $sessions ): array {
		$function_declarations = [];

		foreach ( $sessions as $name => $session ) {
			foreach ( $session->listTools()->tools as $mcp_tool ) {
				$parameters = json_decode(
					json_encode( $mcp_tool->inputSchema->jsonSerialize(), JSON_THROW_ON_ERROR ),
					true,
					512,
					JSON_THROW_ON_ERROR
				);
				unset( $parameters['additionalProperties'], $parameters['$schema'] );

				// Not having any properties doesn't seem to work.
				if ( empty( $parameters['properties'] ) ) {
					$parameters['properties'] = [
						'dummy' => [
							'type' => 'string',
						],
					];
				}

				// FIXME: had some issues with the inputSchema here.
				if ( 'edit_file' === $mcp_tool->name || 'search_files' === $mcp_tool->name ) {
					continue;
				}

				$function_declarations[] = [
					'name'        => $mcp_tool->name,
					'description' => $mcp_tool->description,
					'parameters'  => $parameters,
					'server'      => $name,
					'callback'    => static function ( mixed $tool_args ) use ( $mcp_tool, $session ) {
						$result = $session->callTool( $mcp_tool->name, $tool_args );
						// TODO: Convert ImageContent or EmbeddedResource into Blob?

						// To trigger the jsonSerialize() methods.
						// TODO: Return all array items, not just first one.
						return json_decode(
							json_encode( $result->content[0], JSON_THROW_ON_ERROR ),
							true,
							512,
							JSON_THROW_ON_ERROR
						);
					},
				];
			}
		}

		return $function_declarations;
	}

	/**
	 * Returns a list of MCP client sessions for each MCP server that is configured.
	 *
	 * @param bool $with_wp_server Whether a session for the built-in WordPress MCP server should be created.
	 * @param bool $with_cli_server Whether a session for the built-in WP-CLI MCP server should be created.
	 * @return ClientSession[]
	 */
	public function get_sessions( bool $with_wp_server, bool $with_cli_server ): array {
		$logger = new CliLogger();

		$sessions = [];

		if ( $with_cli_server ) {
			$sessions['current_site'] = ( new Client( $logger ) )->connect(
				MCP\Servers\WP_CLI\WP_CLI::class
			);
		}

		if ( $with_wp_server ) {
			$sessions['wp_cli'] = ( new Client( $logger ) )->connect(
				WordPress::class
			);
		}

		$servers = ( new McpConfig() )->get_servers();

		foreach ( $servers as  $args ) {
			if ( 'active' !== $args['status'] ) {
				continue;
			}

			$server = $args['server'];

			if ( str_starts_with( $server, 'http://' ) || str_starts_with( $server, 'https://' ) ) {
				$sessions[] = ( new Client( $logger ) )->connect(
					$server
				);
				continue;
			}

			$server     = explode( ' ', $server );
			$cmd_or_url = array_shift( $server );

			$sessions[ $args['name'] ] = ( new Client( $logger ) )->connect(
				$cmd_or_url,
				$server,
			);
		}

		return $sessions;
	}
}
