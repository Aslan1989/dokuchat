<?php

declare(strict_types=1);

namespace DokuChat\Tests;

use DokuChat\PromptBuilder;
use PHPUnit\Framework\TestCase;
use InvalidArgumentException;

final class PromptBuilderTest extends TestCase
{
	public function testBuildsSystemAndUserMessages(): void
	{
		$chunks = [
			[
				'filename' => 'museum.txt',
				'position' => 0,
				'content' => 'Das Museum wurde 1893 gegründet.',
			],
		];

		$messages = PromptBuilder::build(
			'Wann wurde das Museum gegründet?',
			$chunks
		);

		$this->assertCount(2, $messages);
		$this->assertSame('system', $messages[0]['role']);
		$this->assertSame('user', $messages[1]['role']);
		$this->assertSame(
			PromptBuilder::SYSTEM_PROMPT,
			$messages[0]['content']
		);
	}
	public function testFormatsSourcesAndQuestion(): void
	{
		$chunks = [
			[
				'filename' => 'museum.txt',
				'position' => 4,
				'content' => 'Der Höhlenbär heißt Brummo.',
			],
			[
				'filename' => 'guide.txt',
				'position' => 0,
				'content' => 'Das Museum wurde 1893 gegründet.',
			],
		];

		$messages = PromptBuilder::build(
			'Wie heißt der Höhlenbär?',
			$chunks
		);

		$userMessage = $messages[1]['content'];

		$this->assertStringContainsString(
			'[1] (museum.txt, Abschnitt 5)',
			$userMessage
		);

		$this->assertStringContainsString(
			'[2] (guide.txt, Abschnitt 1)',
			$userMessage
		);

		$this->assertStringEndsWith(
			'Frage: Wie heißt der Höhlenbär?',
			$userMessage
		);
	}
	public function testRejectsEmptyChunks(): void
	{
		$this->expectException(InvalidArgumentException::class);

		PromptBuilder::build('Вопрос', []);
	}
}
