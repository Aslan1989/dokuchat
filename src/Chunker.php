<?php

declare(strict_types=1);

namespace DokuChat;

use InvalidArgumentException;

/**
 * Splits a text into chunks of at most $size characters.
 *
 * Chunks are built from whole sentences, so a chunk never starts in the
 * middle of a sentence. Only a sentence that is longer than $size is cut,
 * and then between words. The overlap is made of whole sentences too.
 */
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
		// 1. Words: any whitespace (spaces, new lines, tabs) becomes one space.
		$words = preg_split('/\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY);

		if ($words === false) {
			throw new InvalidArgumentException('Text must be valid UTF-8');
		}

		if ($words === []) {
			return [];
		}

		$normalized = implode(' ', $words);

		// 2. Sentences: cut at a space that comes right after . ! or ?
		//    "30.000" stays together, because there is no space after the dot.
		$sentences = preg_split('/(?<=[.!?])\s+/u', $normalized, -1, PREG_SPLIT_NO_EMPTY);

		// 3. A sentence that is too long for one chunk is cut between words.
		$units = [];

		foreach ($sentences as $sentence) {
			if (mb_strlen($sentence, 'UTF-8') <= $this->size) {
				$units[] = $sentence;
				continue;
			}

			$sentenceWords = explode(' ', $sentence);

			foreach ($this->pack($sentenceWords) as $piece) {
				$units[] = $piece;
			}
		}

		// 4. Pack the sentences (and pieces) into chunks.
		return $this->pack($units);
	}

	/**
	 * Joins units with one space into chunks of at most $size characters.
	 * The next chunk starts with the last units of the previous one (overlap).
	 *
	 * @param list<string> $units words or sentences
	 * @return list<string>
	 */
	private function pack(array $units): array
	{
		$chunks = [];
		$index = 0;
		$unitCount = count($units);

		while ($index < $unitCount) {
			$chunkUnits = [];

			// Add units while the chunk still fits. The first unit is always
			// added, even if it is longer than $size (a very long word).
			while ($index < $unitCount) {
				$candidate = implode(' ', [...$chunkUnits, $units[$index]]);

				if ($chunkUnits !== [] && mb_strlen($candidate, 'UTF-8') > $this->size) {
					break;
				}

				$chunkUnits[] = $units[$index];
				$index++;
			}

			$chunks[] = implode(' ', $chunkUnits);

			if ($index >= $unitCount) {
				break;
			}

			// Overlap: go back over the last units of this chunk, as long as
			// they fit into $overlap. Never take all units ($i > 0), otherwise
			// the next chunk would start at the same place: endless loop.
			// And only if at least one new unit still fits after the overlap,
			// otherwise the next chunk would contain only repeated text.
			$nextStart = $index;

			for ($i = count($chunkUnits) - 1; $i > 0; $i--) {
				$overlapText = implode(' ', array_slice($chunkUnits, $i));

				if (mb_strlen($overlapText, 'UTF-8') > $this->overlap) {
					break;
				}

				$withNextUnit = $overlapText . ' ' . $units[$index];

				if (mb_strlen($withNextUnit, 'UTF-8') > $this->size) {
					break;
				}

				$nextStart--;
			}

			$index = $nextStart;
		}

		return $chunks;
	}
}