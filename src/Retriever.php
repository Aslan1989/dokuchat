<?php

declare(strict_types=1);

namespace DokuChat;

use InvalidArgumentException;
use LogicException;

final class Retriever
{
	/**
	 * @param list<float> $query
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
			$score = VectorMath::cosineSimilarity(
				$query,
				$chunk['embedding']
			);

			$chunk['score'] = $score;

			unset($chunk['embedding']);

			$scored[] = $chunk;
		}
		usort(
			$scored,
			fn(array $x, array $y): int => $y['score'] <=> $x['score']
		);

		return array_slice($scored, 0, $k);
	}
}
