<?php

declare(strict_types=1);

use DokuChat\Config;
use DokuChat\RagService;

require __DIR__ . '/../vendor/autoload.php';

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

$start = microtime(true);

$config = Config::fromEnvironment();
$service = RagService::fromConfig($config);

$passed = 0;

foreach ($cases as $case) {
	$question = $case['q'];
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
