<?php

declare(strict_types=1);

use DokuChat\Chunker;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

final class ChunkerTest extends TestCase
{
	public function testSplitsByWordsWithoutOverlap(): void
	{
		$chunker = new Chunker(size: 10, overlap: 0);

		$chunks = $chunker->chunk('one two three four');

		$this->assertSame(['one two', 'three four'], $chunks);
	}
	public function testRepeatTheLastWordsWhenTheyFitIntoTheOverlap(): void
	{
		$chunker = new Chunker(size: 10, overlap: 4);

		$chunks = $chunker->chunk('one two three four five');

		$this->assertSame(['one two', 'two three', 'four five'], $chunks);
	}
	public function testForEmptyText(): void
	{
		$chunker = new Chunker(size: 10, overlap: 0);

		$chunks = $chunker->chunk('');

		$this->assertSame([], $chunks);
	}
	public function testOnlySpaces(): void
	{
		$chunker = new Chunker(size: 10, overlap: 0);

		$chunks = $chunker->chunk("  \n  ");

		$this->assertSame([], $chunks);
	}
	public function testShortTextOnlyOneChunk(): void
	{
		$chunker = new Chunker(size: 100, overlap: 0);

		$chunks = $chunker->chunk('Hallo Welt');

		$this->assertSame(['Hallo Welt'], $chunks);
	}
	public function testSpacesAndNewLinesBetween(): void
	{
		$chunker = new Chunker(size: 100, overlap: 0);

		$chunks = $chunker->chunk("  one\n\ntwo  ");

		$this->assertSame(['one two'], $chunks);
	}
	public function testLongWordAnotherChunk(): void
	{
		$chunker = new Chunker(size: 5, overlap: 0);

		$chunks = $chunker->chunk('a supercalifragilistic b');

		$this->assertSame(['a', 'supercalifragilistic', 'b'], $chunks);
	}
	public function testCountCharactersButNotBytes(): void
	{
		$chunker = new Chunker(size: 11, overlap: 0);

		$chunks = $chunker->chunk('Größe Maße Füße');

		$this->assertSame(['Größe Maße', 'Füße'], $chunks);
	}
	public function testTrap(): void
	{
		$chunker = new Chunker(size: 10, overlap: 5);

		$chunks = $chunker->chunk('abcd efghijkl');

		$this->assertSame(['abcd', 'efghijkl'], $chunks);
	}
	public function testRejectZeroSize(): void
	{
		$this->expectException(InvalidArgumentException::class);

		new Chunker(size: 0, overlap: 0);
	}
	public static function invalidSizes(): array
	{
		return [
			'size is zero' => [0, 0],
			'overlap is Negative' => [10, -1],
			'overlap is equal size' => [10, 10],
		];
	}
	#[DataProvider('invalidSizes')]
	public function testRejectsInvalidSizes(int $size, int $overlap): void
	{
		$this->expectException(InvalidArgumentException::class);

		new Chunker(size: $size, overlap: $overlap);
	}
}
