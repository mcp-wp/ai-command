<?php

namespace McpWp\AiCommand\Utils;

use Psr\Log\LoggerInterface;
use InvalidArgumentException;
use WP_CLI;

class CliLogger implements LoggerInterface {

	/**
	 * System is unusable.
	 *
	 * @param string $message Message.
	 * @param mixed[] $context Context.
	 */
	public function emergency( $message, array $context = [] ): void {
		WP_CLI::error( $message );
	}

	/**
	 * Action must be taken immediately.
	 *
	 * Example: Entire website down, database unavailable, etc. This should
	 * trigger the SMS alerts and wake you up.
	 *
	 * @param string $message Message.
	 * @param mixed[] $context Context.
	 */
	public function alert( $message, array $context = [] ): void {
		WP_CLI::error( $message );
	}

	/**
	 * Critical conditions.
	 *
	 * Example: Application component unavailable, unexpected exception.
	 *
	 * @param string $message Message.
	 * @param mixed[] $context Context.
	 */
	public function critical( $message, array $context = [] ): void {
		WP_CLI::error( $message );
	}

	/**
	 * Runtime errors that do not require immediate action but should typically
	 * be logged and monitored.
	 *
	 * @param string $message Message.
	 * @param mixed[] $context Context.
	 */
	public function error( $message, array $context = [] ): void {
		WP_CLI::error( $message );
	}

	/**
	 * Exceptional occurrences that are not errors.
	 *
	 * Example: Use of deprecated APIs, poor use of an API, undesirable things
	 * that are not necessarily wrong.
	 *
	 * @param string $message Message.
	 * @param mixed[] $context Context.
	 */
	public function warning( $message, array $context = [] ): void {
		WP_CLI::warning( $message );
	}

	/**
	 * Normal but significant events.
	 *
	 * @param string $message Message.
	 * @param mixed[] $context Context.
	 */
	public function notice( $message, array $context = [] ): void {
		WP_CLI::debug( $message, 'ai-command' );
	}

	/**
	 * Interesting events.
	 *
	 * Example: User logs in, SQL logs.
	 *
	 * @param string $message Message.
	 * @param mixed[] $context Context.
	 */
	public function info( $message, array $context = [] ): void {
		WP_CLI::debug( $message, 'ai-command' );
	}

	/**
	 * Detailed debug information.
	 *
	 * @param string $message Message.
	 * @param mixed[] $context Context.
	 */
	public function debug( $message, array $context = [] ): void {
		WP_CLI::debug( $message, 'ai-command' );
	}

	/**
	 * Logs with an arbitrary level.
	 *
	 * @param mixed $level Log level.
	 * @param string $message Message.
	 * @param mixed[] $context Context.
	 *
	 * @throws InvalidArgumentException
	 */
	public function log( $level, $message, array $context = [] ): void {
		WP_CLI::log( $message );
	}
}
