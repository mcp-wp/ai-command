<?php

declare(strict_types=1);

namespace WP_CLI\AiCommand\Entity;

final class Tool
{

	public function __construct(
		private array $data,
		private array $tags = []
	) {
		$this->validate();
	}

	private function validate(): void
	{
		foreach ($this->tags as $tag) {
			if ( ! preg_match('/^[a-z][a-z-]+$/', $tag)) {
				throw new InvalidArgumentException('Tags can only contain [a-z] and -.');
			}
		}
	}

	public function get_name(): string
	{
		return $this->data['name'];
	}

	public function get_tags(): array
	{
		return $this->tags;
	}

	public function get_data(): array
	{
		return $this->data;
	}

}
