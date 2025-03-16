<?php

declare(strict_types=1);

namespace WP_CLI\AiCommand\ToolRepository;

use WP_CLI\AiCommand\Entity\Tool;
use WP_CLI\AiCommand\ToolCollection;
use WP_CLI\AiCommand\ToolRepository;

class CollectionToolRepository implements ToolRepository
{

	public function __construct(
		private ToolCollection $collection
	) {
	}

	public function find(string $name): ?Tool
	{
		foreach ($this->find_all() as $tool) {
			if ($tool->get_name() === $name) {
				return $tool;
			}
		}

		return null;
	}

	public function find_all(array $filters = []): ToolCollection
	{
		$defaults = [
			'include' => 'all',
			'exclude' => [],
		];

		$filters = array_merge($defaults, $filters);

		$filtered = iterator_to_array($this->collection);

		if ($filters['include'] !== 'all') {
			$all = $filtered;
			$filtered = [];

			foreach ($filters['include'] as $tag_to_include) {
				foreach ($all as $tool) {
					foreach ($tool->get_tags() as $tag_to_check) {
						if ($tag_to_include === $tag_to_check) {
							$filtered[$tool->get_name()] = $tool;

							continue 2;
						}
					}
				}
			}
		}

		if ($filters['exclude']) {
			foreach ($filters['exclude'] as $tag_to_exclude) {
				foreach ($filtered as $tool) {
					foreach ($tool->get_tags() as $tag_to_check) {
						if ($tag_to_exclude === $tag_to_check) {
							unset($filtered[$tool->get_name()]);
						}
					}
				}
			}
		}

		return new ToolCollection($filtered);
	}

}
