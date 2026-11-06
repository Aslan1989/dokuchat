<?php

declare(strict_types=1);

namespace DokuChat;

use InvalidArgumentException;
use LogicException;

final class Chunker
{
	public function __construct(
		private readonly int $size = 800,
		private readonly int $overlap = 100,
	) {
		if ($size < 1 || $overlap < 0 || $overlap >= $size) {
			throw new InvalidArgumentException('Invalid size or overlap');
		}
	}
	/**
	 * @return list<string>
	 */
	public function chunk(string $text): array
	{
		$words = preg_split('/\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY);
		
		if($words === false) {
			throw new InvalidArgumentException('Text must be valid UTF-8');
		}
		if ($words === []) {
			return [];
		}
		throw new LogicException('TODO: split words into chunks');
	}
}