<?php

declare(strict_types=1);

namespace DokuChat\Tests;

use DokuChat\VectorMath;
use PHPUnit\Framework\TestCase;
use InvalidArgumentException;

final class VectorMathTest extends TestCase
{
	public function testCosineSimularity(): void
	{
		$result = VectorMath::cosineSimilarity(
			[3, 4],
			[4, 3]
		);

		$this->assertEqualsWithDelta(0.96, $result, 0.0001);
	}

	public function testRejectsDifferentLengths(): void
	{
		$this->expectException(InvalidArgumentException::class);

		VectorMath::cosineSimilarity([1, 2], [1, 2, 3]);
	}
	public function testEmptyArray(): void
	{
		$this->expectException(InvalidArgumentException::class);

		VectorMath::cosineSimilarity([], []);
	}
	public function testRejectZeroVector(): void
	{
		$this->expectException(InvalidArgumentException::class);

		VectorMath::cosineSimilarity([0, 0], [1, 1]);
	}

	public function testSameDirection(): void
	{
		$result = VectorMath::cosineSimilarity(
			[1, 2, 3],
			[2, 4, 6]
		);

		$this->assertEqualsWithDelta(1, $result, 0.0001);
	}

	public function testPerpendecularValues(): void
	{
		$result = VectorMath::cosineSimilarity(
			[1, 0],
			[0, 1]
		);

		$this->assertEqualsWithDelta(0.0, $result, 0.0001);
	}

	public function testOppositeValues(): void
	{
		$result = VectorMath::cosineSimilarity(
			[1, 2],
			[-1, -2]
		);

		$this->assertEqualsWithDelta(-1.0, $result, 0.0001);
	}

	public function testAtTheAngle45(): void
	{
		$result = VectorMath::cosineSimilarity(
			[1, 0],
			[1, 1]
		);

		$this->assertEqualsWithDelta(0.7071, $result, 0.0001);
	}
}
