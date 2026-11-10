<?php

declare(strict_types=1);

use DokuChat\Config;
use DokuChat\RagService;

require __DIR__ . '/../vendor/autoload.php';

/**
 * End-to-end evaluation: asks fixed questions with known answers through the
 * whole RAG pipeline and counts how many answers are correct.
 *
 * Why? When we change something (chunking, k, model, prompt), we need a
 * number that shows whether it got better or worse, not just a feeling.
 *
 * Each case has a question 'q' and EITHER:
 *   'any' => the answer must contain at least ONE of these texts
 *            (the model may write "30.000" or "30000")
 *   'all' => the answer must contain EVERY text
 *            (a question that needs two documents)
 *
 * Special cases:
 *  - "Bürgermeister": the answer is in NO document. It tests that the model
 *    says "nicht in den Dokumenten" instead of inventing something.
 *  - Museum + Bibliotheksausweis: needs chunks from TWO documents, so it
 *    also tests that retrieval finds both.
 */
$cases = [
	[
		'q' => 'Wie alt ist der Höhlenbär?',
		'any' => ['30.000', '30000'],
	],
	[
		'q' => 'Wie heißt der Höhlenbär?',
		'any' => ['Brummo'],
	],
	[
		'q' => 'Wie lange darf ich ein Buch ausleihen?',
		'any' => ['vier Wochen', '4 Wochen'],
	],
	[
		'q' => 'Was kostet der Eintritt für Kinder unter zwölf Jahren?',
		'any' => ['frei', 'kostenlos', '0 Euro'],
	],
	[
		'q' => 'Wann ist die Vorlesestunde für Kinder?',
		'any' => ['Mittwoch'],
	],
	[
		'q' => 'Wer ist der Bürgermeister von Lindenhain?',
		'any' => ['nicht in den Dokumenten'],
	],
	[
		'q' => 'Was kostet der Eintritt ins Museum und was kostet ein Bibliotheksausweis?',
		'all' => ['8 Euro', '12 Euro'],
	],
	[
		'q' => 'Ist das Museum am Montag geöffnet?',
		'any' => ['geschlossen', 'nicht geöffnet'],
	],
	[
		'q' => 'Ist die Bibliothek am Montag geöffnet?',
		'any' => ['9 bis 19', '9 Uhr', '9:00'],
	],
];

/**
 * Checks one answer against one case.
 *
 * mb_stripos = case-insensitive search that understands UTF-8 (umlauts).
 * It returns the position or false, so we must compare with "!== false":
 * position 0 (text at the very start) would otherwise count as "not found".
 *
 * Limit: this only checks keywords. It cannot judge whether the whole
 * answer is good or whether the source numbers [1], [2] are correct.
 */
function passes(string $answer, array $case): bool
{
	if (isset($case['any'])) {
		foreach ($case['any'] as $expected) {
			if (mb_stripos($answer, $expected, 0, 'UTF-8') !== false) {
				return true;
			}
		}

		return false;
	}

	if (isset($case['all'])) {
		foreach ($case['all'] as $expected) {
			if (mb_stripos($answer, $expected, 0, 'UTF-8') === false) {
				return false;
			}
		}

		return true;
	}

	throw new InvalidArgumentException('Case must have any or all');
}

// Measure the total time: speed is part of quality, too.
$start = microtime(true);

$config = Config::fromEnvironment();
$service = RagService::fromConfig($config);

$passed = 0;

foreach ($cases as $case) {
	$question = $case['q'];
	// try/catch per case: if one question fails (e.g. a timeout), it counts
	// as FAIL and the eval continues with the next question.
	try {
		$result = $service->answer($question);

		$answer = $result['answer'];
		$sources = $result['sources'];

		$isPass = passes($answer, $case);
		if($isPass) {
			$passed++;
		}

		$status = $isPass ? 'PASS' : 'FAIL';

		$firstSource = 'Нет источника';

		if ($sources !== []) {
			$firstSource = $sources[0]['filename']
				. ', Abschnitt '
				. ($sources[0]['position'] + 1);
		}

		echo $status . ': ' . $question . PHP_EOL;
		echo 'Antwort: ' . mb_substr($answer, 0, 100, 'UTF-8') . PHP_EOL;
		echo 'Quelle [1]: ' . $firstSource . PHP_EOL . PHP_EOL;
	} catch (Throwable $e) {
		echo 'FAIL: ' . $question . PHP_EOL;
		echo 'Fehler: ' . $e->getMessage() . PHP_EOL . PHP_EOL;
	}
}

$elapsed = microtime(true) - $start;

echo 'Result: ' . $passed . '/' . count($cases) . ' passed' . PHP_EOL;
printf("Time: %.2f seconds\n", $elapsed);
