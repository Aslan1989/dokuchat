<?php

declare(strict_types=1);

namespace DokuChat;

use InvalidArgumentException;
use LogicException;

/**
 * The "R" in RAG: Retrieval = finding the chunks that fit the question best.
 */
final class Retriever
{
	/**
	 * Scores every chunk against the question and returns the k best.
	 *
	 * This is "brute force" search: compare the question with EVERY chunk.
	 * Simple and exact. It is fine for a small number of chunks; with
	 * millions, databases use a vector index that searches approximately.
	 *
	 * @param list<float> $query the embedding of the question
	 * @param list<array<string, mixed>> $chunks each chunk has an 'embedding' key
	 * @return list<array<string, mixed>> best chunks first, each with a 'score' key
	 */
	public static function rank(array $query, array $chunks, int $k): array
	{
		if ($k < 1) {
			throw new InvalidArgumentException('k must be at least 1');
		}

		$scored = [];

		foreach ($chunks as $chunk) {
			// How close is the meaning of this chunk to the question?
			$score = VectorMath::cosineSimilarity(
				$query,
				$chunk['embedding']
			);

			$chunk['score'] = $score;

			// The vector is not needed any more (the prompt only needs the
			// text). Removing it keeps the result small. $chunk is a copy,
			// so the original array is not changed.
			unset($chunk['embedding']);

			$scored[] = $chunk;
		}

		// Sort from highest to lowest score.
		// <=> (spaceship) returns -1, 0 or 1. $y <=> $x (not $x <=> $y)
		// reverses the order, so the best chunk comes first.
		usort(
			$scored,
			fn(array $x, array $y): int => $y['score'] <=> $x['score']
		);

		// Keep only the first k. If there are fewer than k chunks,
		// array_slice simply returns all of them.
		return array_slice($scored, 0, $k);
	}
}
