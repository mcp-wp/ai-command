<?php

namespace WP_CLI\AiCommand\Utils;

use Psr\Log\LoggerInterface;
use InvalidArgumentException;
use WP_CLI;

class CliLogger implements LoggerInterface {

	/**
	 * System is unusable.
	 *
	 * @param mixed[] $context
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
	 * @param mixed[] $context
	 */
	public function alert( $message, array $context = [] ): void {
		WP_CLI::error( $message );
	}

	/**
	 * Critical conditions.
	 *
	 * Example: Application component unavailable, unexpected exception.
	 *
	 * @param mixed[] $context
	 */
	public function critical( $message, array $context = [] ): void {
		WP_CLI::error( $message );
	}

	/**
	 * Runtime errors that do not require immediate action but should typically
	 * be logged and monitored.
	 *
	 * @param mixed[] $context
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
	 * @param mixed[] $context
	 */
	public function warning( $message, array $context = [] ): void {
		WP_CLI::warning( $message );
	}

	/**
	 * Normal but significant events.
	 *
	 * @param mixed[] $context
	 */
	public function notice( $message, array $context = [] ): void {
		WP_CLI::log( $message );
	}

	/**
	 * Interesting events.
	 *
	 * Example: User logs in, SQL logs.
	 *
	 * @param mixed[] $context
	 */
	public function info( $message, array $context = [] ): void {
		WP_CLI::log( $message );
	}

	/**
	 * Detailed debug information.
	 *
	 * @param mixed[] $context
	 */
	public function debug( $message, array $context = [] ): void {
		WP_CLI::debug( $message, 'ai-command' );
	}

	/**
	 * Logs with an arbitrary level.
	 *
	 * @param mixed $level
	 * @param mixed[] $context
	 *
	 * @throws InvalidArgumentException
	 */
	public function log( $level, $message, array $context = [] ): void {
		WP_CLI::log( $message );
	}
}
