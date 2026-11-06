<?php

declare(strict_types=1);

namespace DokuChat;
use DokuChat\Greeter;
use DokuChat\Config;
use DokuChat\Database;
use DokuChat\LlmClient;
use DokuChat\PromptBuilder;
use DokuChat\DocumentRepository;
use DokuChat\Retriever;
use PDOException;
use RuntimeException;



require __DIR__ . '/../vendor/autoload.php';

$method = $_SERVER['REQUEST_METHOD'];
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$config = Config::fromEnvironment();

match ("$method $path") {
	'GET /' => homePage(),
	'GET /hello' => helloPage(),
	'GET /health' => healthCheck($config),
	'GET /ask' => askPage($config),
	default => notFound(),
};

function askPage(Config $config): void
{
	$question = $_GET['q'] ?? '';

	if (!is_string($question) || trim($question) === '') {
		http_response_code(400);
		echo 'Parameter q is required';
		return;
	}

	$question = trim($question);

	if (mb_strlen($question, 'UTF-8') > 500) {
		http_response_code(400);
		echo 'Question is too long';
		return;
	}

	try {
		$service = RagService::fromConfig($config);
		$result = $service->answer($question);

		$answer = $result['answer'];
		$sources = $result['sources'];

		if ($sources === []) {
			echo htmlspecialchars(
				$answer,
				ENT_QUOTES | ENT_SUBSTITUTE,
				'UTF-8'
			);
			return;
		}

		echo '<p>'
			. htmlspecialchars($answer, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
			. '</p>';

		echo '<h2>Quellen</h2>';
		echo '<ol>';

		foreach ($sources as $chunk) {
			$filename = htmlspecialchars(
				$chunk['filename'],
				ENT_QUOTES | ENT_SUBSTITUTE,
				'UTF-8'
			);

			$section = (int) $chunk['position'] + 1;
			$score = number_format($chunk['score'], 3, '.', '');

			echo '<li>'
				. $filename
				. ', Abschnitt ' . $section
				. ' (Score ' . $score . ')'
				. '</li>';
		}

		echo '</ol>';
	} catch (PDOException $e) {
		error_log('Database error in /ask: ' . $e->getMessage());
		http_response_code(503);
		echo 'Die Datenbank ist gerade nicht erreichbar.';
	} catch (RuntimeException $e) {
		error_log('Error in /ask: ' . $e->getMessage());
		http_response_code(502);
		echo 'Das Sprachmodell ist gerade nicht erreichbar.';
	}
}
function databaseIsUp(Config $config): bool
{
	try {
		Database::connect($config)->query('SELECT 1');
		return true;
	} catch (PDOException $e) {
		error_log('Database check failed: ' . $e->getMessage());
		return false;
	}
}
function healthCheck(Config $config): void
{
	header('Content-Type: application/json; charset=utf-8');
	header('Cache-Control: no-store');
	if (databaseIsUp($config)) {
		http_response_code(200);
		$response = [
			'status' => 'ok',
			'database' => 'ok',
		];
	} else {
		http_response_code(503);
		$response = [
			'status' => 'error',
			'database' => 'down',
		];
	}
	echo json_encode($response, JSON_THROW_ON_ERROR);
}
function homePage(): void
{
	echo 'DockuChat home';
}

function notFound(): void
{
	http_response_code(404);
	echo '404 - not found';
}

function helloPage(): void
{
	$name = $_GET['name'] ?? '';

	// defence from param like ?name[]=Aslan
	if (!is_string($name)) {
		$name = '';
	}
	$text = (new Greeter())->greet($name);
	echo htmlspecialchars($text);
}
