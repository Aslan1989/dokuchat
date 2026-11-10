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

/*
 * Front controller: nginx sends EVERY request to this one file.
 * Here we look at the method and path and call the right function
 * ("routing"), like a framework router, but by hand.
 *
 * The functions are defined further down. PHP knows them already here,
 * because functions declared at the top level of a file are loaded before
 * the file runs.
 */
$method = $_SERVER['REQUEST_METHOD'];
// parse_url(..., PHP_URL_PATH) removes the query string:
// "/ask?q=Hallo" becomes "/ask".
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$config = Config::fromEnvironment();

// match compares strictly (===) and has no fall-through, unlike switch.
// "default" catches every unknown route -> 404.
match ("$method $path") {
	'GET /' => homePage(),
	'GET /hello' => helloPage(),
	'GET /health' => healthCheck($config),
	'GET /ask' => askPage($config),
	'GET /api/ask' => askJson($config),
	default => notFound(),
};

/**
 * GET /ask?q=... : answers a question and returns simple HTML
 * (answer + numbered list of sources with their scores).
 */
function askPage(Config $config): void
{
	$question = $_GET['q'] ?? '';

	// Input validation. is_string(): "?q[]=x" would make $question an
	// array. 400 = "Bad Request": the client sent something wrong.
	if (!is_string($question) || trim($question) === '') {
		http_response_code(400);
		echo 'Parameter q is required';
		return;
	}

	$question = trim($question);

	// Limit the length: a huge question would make the prompt (and the
	// cost / time of the model call) huge as well.
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

		// No sources = no documents loaded yet. Show only the message.
		// htmlspecialchars() everywhere: the answer and filenames are
		// turned into safe HTML (< becomes &lt;), so a model answer or a
		// filename cannot inject <script> into the page (XSS protection).
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
	// Order matters: PDOException is a subclass of RuntimeException, so it
	// must be caught FIRST. Otherwise the RuntimeException block would
	// catch database errors too.
	// The real error goes to the log (for us). The user only sees a short
	// message, so no internal details (paths, SQL, keys) leak out.
	// 503 = "Service Unavailable" (our database), 502 = "Bad Gateway"
	// (the server behind us, the language model, failed).
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
/**
 * Tries to connect and run the cheapest possible query.
 * True = the database answers.
 */
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
/**
 * GET /health : for monitoring tools and Docker. They read the status code:
 * 200 = everything OK, 503 = the database is down.
 * It does NOT call the language model, so it stays fast and free.
 */
function healthCheck(Config $config): void
{
	header('Content-Type: application/json; charset=utf-8');
	// no-store: never cache a health result, it must always be fresh.
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
/**
 * GET / : sends the static HTML page. Its JavaScript (public/assets/app.js)
 * calls /api/ask and shows the answer without reloading the page.
 */
function homePage(): void
{
	header('Content-Type: text/html; charset=utf-8');
	readfile(__DIR__ . '/../templates/home.html');
}

/**
 * Sends a JSON response with a status code. One helper, so every JSON
 * answer has the same headers.
 * JSON_UNESCAPED_UNICODE: "ä" stays "ä" instead of "ä".
 */
function jsonResponse(int $status, array $data): void
{
	http_response_code($status);
	header('Content-Type: application/json; charset=utf-8');
	header('Cache-Control: no-store');
	echo json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
}

/**
 * GET /api/ask?q=... : same as /ask, but returns JSON for the JavaScript
 * front end. Same validation and same error codes as askPage().
 * No htmlspecialchars() here: JSON is data, not HTML. The JavaScript must
 * insert it safely (as text, not as HTML).
 */
function askJson(Config $config): void
{
	$question = $_GET['q'] ?? '';

	if (!is_string($question) || trim($question) === '') {
		jsonResponse(400, ['error' => 'Bitte gib eine Frage ein.']);
		return;
	}

	$question = trim($question);

	if (mb_strlen($question, 'UTF-8') > 500) {
		jsonResponse(400, ['error' => 'Die Frage ist zu lang (maximal 500 Zeichen).']);
		return;
	}

	try {
		$result = RagService::fromConfig($config)->answer($question);
	} catch (PDOException $e) {
		error_log('Database error in /api/ask: ' . $e->getMessage());
		jsonResponse(503, ['error' => 'Die Datenbank ist gerade nicht erreichbar.']);
		return;
	} catch (RuntimeException $e) {
		error_log('Error in /api/ask: ' . $e->getMessage());
		jsonResponse(502, ['error' => 'Das Sprachmodell ist gerade nicht erreichbar.']);
		return;
	}

	// Send only what the page needs: no internal ids, and a short excerpt
	// instead of the full chunk text.
	$sources = array_map(
		fn(array $chunk): array => [
			'filename' => $chunk['filename'],
			'section' => (int) $chunk['position'] + 1,
			'score' => round($chunk['score'], 3),
			'excerpt' => excerpt($chunk['content'], 400),
		],
		$result['sources']
	);

	jsonResponse(200, ['answer' => $result['answer'], 'sources' => $sources]);
}

/**
 * Shortens a text to $length characters and adds " …".
 * mb_ functions count characters, not bytes, so an umlaut (2 bytes in
 * UTF-8) is never cut in half.
 */
function excerpt(string $text, int $length): string
{
	if (mb_strlen($text, 'UTF-8') <= $length) {
		return $text;
	}

	return rtrim(mb_substr($text, 0, $length, 'UTF-8')) . ' …';
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