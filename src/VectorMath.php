<?php

declare(strict_types=1);

namespace DokuChat;

use InvalidArgumentException;

final class VectorMath
{
	/**
	 * Cosine similarity: how similar is the DIRECTION of two vectors?
	 *
	 * Formula:  cos = (a · b) / (|a| * |b|)
	 *   a · b = dot product   = a[0]*b[0] + a[1]*b[1] + ...
	 *   |a|   = length of a   = sqrt(a[0]² + a[1]² + ...)
	 *
	 * Result between -1 and 1:
	 *    1 = same direction  -> very similar meaning
	 *    0 = at right angles -> unrelated
	 *   -1 = opposite direction
	 *
	 * Why cosine and not plain distance? Dividing by the lengths removes the
	 * effect of the vector's size. Only the direction counts, and with
	 * embeddings the direction carries the meaning.
	 *
	 * Example: a = [3, 4], b = [3, 4]
	 *   dot = 9 + 16 = 25, |a| = sqrt(25) = 5, |b| = 5 -> 25 / 25 = 1.0
	 *
	 * @param list<float> $a
	 * @param list<float> $b
	 */
	public static function cosineSimilarity(array $a, array $b): float
	{
		// Vectors of different length cannot be compared. This happens when
		// the chunks were embedded with one model and the question with
		// another. Better a clear error than a meaningless score.
		if ($a === [] || count($a) !== count($b)) {
			throw new InvalidArgumentException(
				'Vectors must be non-empty and have the same length'
			);
		}
		$dotProduct = 0.0;
		$sumA = 0.0;
		$sumB = 0.0;

		// One loop computes all three sums at the same time.
		foreach ($a as $i => $value) {
			$dotProduct += $value * $b[$i]; // a · b
			$sumA += $value * $value;       // a[0]² + a[1]² + ...
			$sumB += $b[$i] * $b[$i];       // b[0]² + b[1]² + ...
		}

		// A zero vector has length 0: we would divide by zero.
		// It also has no direction, so "similarity" makes no sense.
		if ($sumA == 0.0 || $sumB == 0.0) {
			throw new InvalidArgumentException('Zero vector is not allowed');
		}
		$lengthA = sqrt($sumA); // sqrt(25) = 5
		$lengthB = sqrt($sumB); // sqrt(25) = 5

		return $dotProduct / ($lengthA * $lengthB);
	}
}
