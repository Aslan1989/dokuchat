<?php

declare(strict_types=1);

namespace DokuChat;

use InvalidArgumentException;
use LogicException;

final class PromptBuilder
{
	public const string SYSTEM_PROMPT = <<<'TXT'
		Du beantwortest Fragen nur mit Hilfe der Quellen in der Nachricht.
		Nenne nach jeder Aussage die Nummer der Quelle in eckigen Klammern, zum Beispiel [1].
		Wenn die Antwort nicht in den Quellen steht, antworte genau: Das steht nicht in den Dokumenten.
		Antworte kurz und auf Deutsch.
		TXT;

	/**
	 * @param list<array{filename: string, position: int, content: string}> $chunks
	 * @return list<array{role: string, content: string}>
	 */
	public static function build(string $question, array $chunks): array
	{
		if ($chunks === []) {
			throw new InvalidArgumentException('No sources provided');
		}
		$sources = [];

		foreach ($chunks as $index => $chunk) {
			$sourceNumber = $index + 1;
			$sectionNumber = $chunk['position'] + 1;

			$sources[] = sprintf(
				"[%d] (%s, Abschnitt %d)\n%s",
				$sourceNumber,
				$chunk['filename'],
				$sectionNumber,
				$chunk['content']
			);
		}

		$userMessage = "Quellen:\n\n"
			. implode("\n\n", $sources)
			. "\n\nFrage: "
			. $question;

		return [
			[
				'role' => 'system',
				'content' => self::SYSTEM_PROMPT,
			],
			[
				'role' => 'user',
				'content' => $userMessage,
			],
		];
	}
}
