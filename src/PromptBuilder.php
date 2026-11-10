<?php

declare(strict_types=1);

namespace DokuChat;

use InvalidArgumentException;
use LogicException;

/**
 * Builds the messages for the language model: the "A" in RAG
 * (Augmentation = adding the found chunks to the prompt).
 */
final class PromptBuilder
{
	/**
	 * The rules for the model ("system prompt"). Each line has a purpose:
	 *  1. Only use the sources        -> no invented facts ("hallucinations")
	 *  2. Cite sources like [1]       -> the user can check the answer
	 *  3. An EXACT sentence if the answer is missing -> the model can say
	 *     "I don't know", and the eval can test that with a fixed text
	 *  4. Short and in German         -> the documents are German
	 *
	 * <<<'TXT' is a nowdoc: a multi-line string where PHP replaces nothing
	 * (no $variables). The indentation before the closing TXT is removed.
	 */
	public const string SYSTEM_PROMPT = <<<'TXT'
		Du beantwortest Fragen nur mit Hilfe der Quellen in der Nachricht.
		Nenne nach jeder Aussage die Nummer der Quelle in eckigen Klammern, zum Beispiel [1].
		Wenn die Antwort nicht in den Quellen steht, antworte genau: Das steht nicht in den Dokumenten.
		Antworte kurz und auf Deutsch.
		TXT;

	/**
	 * Returns two messages: the system message (rules) and the user message
	 * (numbered sources + question).
	 *
	 * Example user message:
	 *   Quellen:
	 *
	 *   [1] (museum.txt, Abschnitt 1)
	 *   Das Museum ...
	 *
	 *   [2] (library.txt, Abschnitt 2)
	 *   Die Bibliothek ...
	 *
	 *   Frage: Wie alt ist der Höhlenbär?
	 *
	 * @param list<array{filename: string, position: int, content: string}> $chunks
	 * @return list<array{role: string, content: string}>
	 */
	public static function build(string $question, array $chunks): array
	{
		// Without sources the model can only guess. RagService checks this
		// earlier; this is a second safety net.
		if ($chunks === []) {
			throw new InvalidArgumentException('No sources provided');
		}
		$sources = [];

		foreach ($chunks as $index => $chunk) {
			// People count from 1, PHP arrays from 0.
			// [1] is the best chunk, because Retriever sorted them.
			$sourceNumber = $index + 1;
			$sectionNumber = $chunk['position'] + 1;

			// Filename and section are in the prompt, so the model (and the
			// user) can see where each piece of text comes from.
			$sources[] = sprintf(
				"[%d] (%s, Abschnitt %d)\n%s",
				$sourceNumber,
				$chunk['filename'],
				$sectionNumber,
				$chunk['content']
			);
		}

		// Sources first, question last: the model reads the material and
		// then sees what it should do with it.
		$userMessage = "Quellen:\n\n"
			. implode("\n\n", $sources)
			. "\n\nFrage: "
			. $question;

		// Rules go into the "system" role: models treat system messages as
		// instructions with higher priority than the user text. This makes
		// it a bit harder for document text to override the rules
		// (prompt injection), but it is not full protection.
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
