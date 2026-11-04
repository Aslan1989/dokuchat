<?php

declare(strict_types=1);

use DokuChat\Greeter;
use DokuChat\Config;
use DokuChat\Database;
use DokuChat\LlmClient;



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
	if ($question === '') {
		http_response_code(404);
		echo 'Parameter q is required';
		return;
	}
	if (mb_strlen($question, 'UTF-8') > 500) {
		http_response_code(404);
		echo 'Question is too long';
		return;
	}
	try {
		$answer = LlmClient::fromConfig($config)->chat([
			['role' => 'system', 'content' => 'Answer briefly, in at most three sentences.'],
			['role' => 'user', 'content' => $question],
		]);
		echo htmlspecialchars($answer);
	} catch (RuntimeException $e) {
		error_log('LLM request failed: ' . $e->getMessage());
		http_response_code(502);
		echo 'The language model is not available right now';
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
