<?php

declare(strict_types=1);

namespace WP_CLI\AiCommand;

use WP_CLI\AiCommand\Entity\Tool;

interface ToolRepository
{

	public function find(string $name): ?Tool;

	/**
	 * Controls which tags are included or excluded from processing.
	 *
	 * Keys allowed:
	 * - `include`: Defines which tags to include. Accepts:
	 *   - `'all'` (default): includes all available tags.
	 *   - an empty string or empty array: includes nothing.
	 *   - a string of comma-separated tag names or an array of tag names: includes only those.
	 *
	 * - `exclude`: Removes tags from the resolved include list. Can be a string or array of tag names.
	 *
	 * Behavior:
	 * - Inclusion is resolved first.
	 * - Exclusion is applied afterward to filter out specific tags from the inclusion list.
	 */
	public function find_all(array $filters = []): ToolCollection;

}
