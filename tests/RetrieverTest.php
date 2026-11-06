<?php

declare(strict_types=1);

namespace DokuChat\Tests;

use DokuChat\Retriever;
use PHPUnit\Framework\TestCase;
use InvalidArgumentException;

final class RetrieverTest extends TestCase
{
	private static function chunks(): array
	{
		return [
			['id' => 1, 'embedding' => [1.0, 0.0]],
			['id' => 2, 'embedding' => [0.0, 1.0]],
			['id' => 3, 'embedding' => [1.0, 1.0]],
		];
	}

	public function testRanksChunksBySimilarity(): void
	{
		$query = [1.0, 0.0];

		$result = Retriever::rank($query, self::chunks(), 3);

		$ids = array_column($result, 'id');

		$this->assertSame([1, 3, 2], $ids);
	}
	public function testReturnsOnlyTopKChunks(): void
	{
		$query = [1.0, 0.0];

		$result = Retriever::rank($query, self::chunks(), 2);

		$ids = array_column($result, 'id');

		$this->assertSame([1, 3], $ids);
	}
	public function testRejectsZeroK(): void
	{
		$this->expectException(InvalidArgumentException::class);

		Retriever::rank([1.0, 0.0], self::chunks(), 0);
	}
	public function testReturnsSimilarityScores(): void
	{
		$result = Retriever::rank([1.0, 0.0], self::chunks(), 3);

		$this->assertEqualsWithDelta(1.0, $result[0]['score'], 0.0001);
		$this->assertEqualsWithDelta(0.7071, $result[1]['score'], 0.0001);
	}
	public function testDoesNotReturnEmbedding(): void
	{
		$result = Retriever::rank([1.0, 0.0], self::chunks(), 3);

		$this->assertArrayNotHasKey('embedding', $result[0]);
	}
	public function testReturnsEmptyArrayWhenThereAreNoChunks(): void
	{
		$result = Retriever::rank([1.0, 0.0], [], 3);

		$this->assertSame([], $result);
	}
	public function testKeepsOriginalOrderWhenScoresAreEqual(): void
	{
		$chunks = [
			['id' => 4, 'embedding' => [1.0, 1.0]],
			['id' => 5, 'embedding' => [1.0, 1.0]],
		];

		$result = Retriever::rank([1.0, 0.0], $chunks, 2);

		$this->assertSame([4, 5], array_column($result, 'id'));
	}
}
