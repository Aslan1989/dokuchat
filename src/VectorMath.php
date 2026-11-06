<?php

declare(strict_types=1);

namespace DokuChat;

use InvalidArgumentException;

final class VectorMath
{
	/**
	 * @param list<float> $a
	 * @param list<float> $b
	 */
	public static function cosineSimilarity(array $a, array $b): float
	{
		if ($a === [] || count($a) !== count($b)) {
			throw new InvalidArgumentException(
				'Vectors must be non-empty and have the same length'
			);
		}
		$dotProduct = 0.0;
		$sumA = 0.0;
		$sumB = 0.0;
		foreach ($a as $i => $value) {
			$dotProduct += $value * $b[$i];
			$sumA += $value * $value;
			$sumB += $b[$i] * $b[$i];
		}
		if ($sumA == 0.0 || $sumB == 0.0) {
			throw new InvalidArgumentException('Zero vector is not allowed');
		}
		$lengthA = sqrt($sumA); // sqrt(25) = 5
		$lengthB = sqrt($sumB); // sqrt(25) = 5

		return $dotProduct / ($lengthA * $lengthB);
	}
}
