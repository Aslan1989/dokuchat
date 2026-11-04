<?php
declare(strict_types=1);

use DokuChat\Greeter;

require __DIR__ . '/../vendor/autoload.php';

$method = $_SERVER['REQUEST_METHOD'];
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

match ("$method $path") {
	'GET /' => homePage(),
	'GET /hello' => helloPage(),
	default => notFound(),
};

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