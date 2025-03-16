<?php

declare(strict_types=1);

namespace WP_CLI\AiCommand;

use Countable;
use Iterator;

abstract class Collection implements Iterator, Countable
{

	protected array $data = [];

	public function next(): void
	{
		next($this->data);
	}

	public function key(): int
	{
		return (int)key($this->data);
	}

	public function valid(): bool
	{
		return key($this->data) !== null;
	}

	public function rewind(): void
	{
		reset($this->data);
	}

	public function count(): int
	{
		return count($this->data);
	}

}
