<?php
declare(strict_types=1);

namespace DokuChat;

use LogicException;
use RuntimeException;

final class Config
{
	/** @param array<string, string> $values */
	public function __construct(private readonly array $values)
	{
		
	}
	public static function fromEnvironment(): self
	{
		return new self(getenv());
	}

	public function get(string $key, ?string $default = null): string
	{
		$value = trim($this->values[$key] ?? '');
		if ($value !== '') {
			return $value;
		}
		if ($default !== null) {
			return $default;
		}
		throw new RuntimeException("Missing configuration: $key");
	}
}