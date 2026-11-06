<?php

declare(strict_types=1);

use DokuChat\Config;
use DokuChat\RagService;

require __DIR__ . '/../vendor/autoload.php';

$cases = [
	['Wie alt ist der Höhlenbär?', ['30.000', '30000']],
	['Wie heißt der Höhlenbär?', ['Brummo']],
	['Wie lange darf ich ein Buch ausleihen?', ['vier Wochen', '4 Wochen']],
	['Was kostet der Eintritt für Kinder unter zwölf Jahren?', ['frei', 'kostenlos', '0 Euro']],
	['Wann ist die Vorlesestunde für Kinder?', ['Mittwoch']],
	['Wer ist der Bürgermeister von Lindenhain?', ['nicht in den Dokumenten']],
];

$start = microtime(true);

$config = Config::fromEnvironment();
$service = RagService::fromConfig($config);

$passed = 0;

foreach ($cases as [$question, $expectedWords]) {
	try {
		$result = $service->answer($question);

		$answer = $result['answer'];
		$sources = $result['sources'];

		$isPass = false;

		foreach ($expectedWords as $expected) {
			if (mb_stripos($answer, $expected, 0, 'UTF-8') !== false) {
				$isPass = true;
				break;
			}
		}

		if ($isPass) {
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
