<?php

declare(strict_types=1);

namespace DokuChat\Tests;

use DokuChat\Chunker;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ChunkerTest extends TestCase
{
	// --- splitting by words (texts without sentence ends) ----------------

	public function testSplitsByWordsWithoutOverlap(): void
	{
		$chunker = new Chunker(size: 10, overlap: 0);

		$chunks = $chunker->chunk('one two three four');

		$this->assertSame(['one two', 'three four'], $chunks);
	}

	public function testRepeatsTheLastWordsWhenTheyFitIntoTheOverlap(): void
	{
		$chunker = new Chunker(size: 10, overlap: 4);

		$chunks = $chunker->chunk('one two three four five');

		$this->assertSame(['one two', 'two three', 'four five'], $chunks);
	}

	public function testReturnsNoChunksForEmptyText(): void
	{
		$chunker = new Chunker(size: 10, overlap: 0);

		$this->assertSame([], $chunker->chunk(''));
	}

	public function testReturnsNoChunksForOnlySpaces(): void
	{
		$chunker = new Chunker(size: 10, overlap: 0);

		$this->assertSame([], $chunker->chunk("  \n  "));
	}

	public function testKeepsAShortTextInOneChunk(): void
	{
		$chunker = new Chunker(size: 100, overlap: 0);

		$this->assertSame(['Hallo Welt'], $chunker->chunk('Hallo Welt'));
	}

	public function testTurnsSpacesAndNewLinesIntoOneSpace(): void
	{
		$chunker = new Chunker(size: 100, overlap: 0);

		$this->assertSame(['one two'], $chunker->chunk("  one\n\ntwo  "));
	}

	public function testPutsAWordLongerThanTheSizeIntoItsOwnChunk(): void
	{
		$chunker = new Chunker(size: 5, overlap: 0);

		$chunks = $chunker->chunk('a supercalifragilistic b');

		$this->assertSame(['a', 'supercalifragilistic', 'b'], $chunks);
	}

	public function testCountsCharactersNotBytes(): void
	{
		$chunker = new Chunker(size: 11, overlap: 0);

		$chunks = $chunker->chunk('Größe Maße Füße');

		$this->assertSame(['Größe Maße', 'Füße'], $chunks);
	}

	public function testAlwaysMovesForwardEvenWhenAChunkFitsIntoTheOverlap(): void
	{
		$chunker = new Chunker(size: 10, overlap: 5);

		$chunks = $chunker->chunk('abcd efghijkl');

		$this->assertSame(['abcd', 'efghijkl'], $chunks);
	}

	public function testDoesNotCreateAChunkThatIsOnlyOverlap(): void
	{
		$chunker = new Chunker(size: 10, overlap: 9);

		$chunks = $chunker->chunk('abc defg hijklmno');

		$this->assertSame(['abc defg', 'hijklmno'], $chunks);
	}

	// --- splitting by sentences -----------------------------------------

	public function testDoesNotCutASentenceInTheMiddle(): void
	{
		// The old word-based version returned ['Erster Satz. Zweiter', 'Satz.']
		$chunker = new Chunker(size: 20, overlap: 0);

		$chunks = $chunker->chunk('Erster Satz. Zweiter Satz.');

		$this->assertSame(['Erster Satz.', 'Zweiter Satz.'], $chunks);
	}

	public function testBuildsTheOverlapFromWholeSentences(): void
	{
		$chunker = new Chunker(size: 12, overlap: 6);

		$chunks = $chunker->chunk('Eins. Zwei. Drei.');

		$this->assertSame(['Eins. Zwei.', 'Zwei. Drei.'], $chunks);
	}

	public function testTreatsQuestionAndExclamationMarksAsSentenceEnds(): void
	{
		$chunker = new Chunker(size: 9, overlap: 0);

		$chunks = $chunker->chunk('Wer? Ich! Gut.');

		$this->assertSame(['Wer? Ich!', 'Gut.'], $chunks);
	}

	public function testDoesNotSplitAtTheDotInsideANumber(): void
	{
		$chunker = new Chunker(size: 100, overlap: 0);

		$chunks = $chunker->chunk('Es ist 30.000 Jahre alt.');

		$this->assertSame(['Es ist 30.000 Jahre alt.'], $chunks);
	}

	public function testCutsOnlyASentenceThatIsTooLongBetweenWords(): void
	{
		$chunker = new Chunker(size: 12, overlap: 0);

		$chunks = $chunker->chunk('Kurz. Dieser Satz ist zu lang. Ende.');

		$this->assertSame(['Kurz.', 'Dieser Satz', 'ist zu lang.', 'Ende.'], $chunks);
	}

	public function testPutsEveryChunkAfterTheFirstAtTheStartOfASentence(): void
	{
		$text = 'Das Museum wurde 1893 gegründet. Der Eintritt kostet 8 Euro, ermäßigt 4 Euro. '
			. 'Kinder unter zwölf Jahren haben freien Eintritt. Montags bleibt es geschlossen.';
		$chunker = new Chunker(size: 90, overlap: 50);

		$chunks = $chunker->chunk($text);

		$this->assertGreaterThan(1, count($chunks));

		foreach ($chunks as $chunk) {
			$this->assertMatchesRegularExpression('/^\p{Lu}/u', $chunk, "Starts mid-sentence: $chunk");
			$this->assertMatchesRegularExpression('/[.!?]$/', $chunk, "Ends mid-sentence: $chunk");
		}
	}

	// --- invalid settings -------------------------------------------------

	/** @return array<string, array{int, int}> */
	public static function invalidSettings(): array
	{
		return [
			'size is zero' => [0, 0],
			'overlap is negative' => [10, -1],
			'overlap equals size' => [10, 10],
		];
	}

	#[DataProvider('invalidSettings')]
	public function testRejectsInvalidSettings(int $size, int $overlap): void
	{
		$this->expectException(InvalidArgumentException::class);

		new Chunker(size: $size, overlap: $overlap);
	}
}