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
		$chunks = [];
		$index = 0;
		$wordCount = count($words);

		while ($index < $wordCount) {
			$chunksWords = [];
			while ($index < $wordCount) {
				$candidateWords = $chunksWords;
				$candidateWords[] = $words[$index];

				$candidate = implode(' ', $candidateWords);

				if ($chunksWords !== [] && mb_strlen($candidate, 'UTF-8') > $this->size) {
					break;
				}
				$chunksWords[] = $words[$index];
				$index++;
			}
			$chunks[] = implode(' ', $chunksWords);
			if ($index < $wordCount) {
				$nextStart = $index;
				for ($i = count($chunksWords) - 1; $i > 0; $i--) {
					$overlapWords = array_slice($chunksWords, $i);
					$overlapText = implode(' ', $overlapWords);
					if (mb_strlen($overlapText, 'UTF-8') > $this->overlap) {
						break;
					}
					$candidate = $overlapText . ' ' . $words[$index];
					if (mb_strlen($candidate, 'UTF-8') > $this->size) {
						break;
					}
					$nextStart--;
				}
				$index = $nextStart;
			}
		}
		return $chunks;
	}
}