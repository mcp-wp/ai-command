<?php

declare(strict_types=1);

namespace WP_CLI\AiCommand;

use WP_CLI\AiCommand\Entity\Tool;

final class ToolCollection extends Collection
{

	public function __construct(array $data = [])
	{
		foreach ($data as $tool) {
			$this->add($tool);
		}
	}

	public function add(Tool $tool): void
	{
		$this->data[] = $tool;
	}

	public function current(): Tool
	{
		return current($this->data);
	}

}
