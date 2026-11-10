<?php

declare(strict_types=1);

use DokuChat\Chunker;
use DokuChat\Config;
use DokuChat\Database;
use DokuChat\DocumentRepository;

require __DIR__ . '/../vendor/autoload.php';

function fail(string $message): never
{
	fwrite(STDERR, 'Error: ' . $message . PHP_EOL);
	exit(1);
}

$path = $argv[1] ?? null;

if ($path === null) {
	fwrite(STDERR, 'Usage: php bin/ingest.php <file>' . PHP_EOL);
	exit(1);
}

if (!is_file($path) || !is_readable($path)) {
	fail('File does not exist or is not readable');
}

$text = file_get_contents($path);

if ($text === false) {
	fail('Could not read file');
}

if (!mb_check_encoding($text, 'UTF-8')) {
	fail('File is not valid UTF-8');
}

$chunker = new Chunker();
$chunks = $chunker->chunk($text);

if ($chunks === []) {
	fail('File contains no text');
}

$config = Config::fromEnvironment();
$pdo = Database::connect($config);

$repository = new DocumentRepository($pdo);

$filename = basename($path);

$existingId = $repository->findIdByFilename($filename);

if ($existingId !== null) {
	fail('"' . $filename . '" is already saved as document ' . $existingId . '. Delete it first.');
}

$documentId = $repository->save($filename, $chunks);

echo 'Saved document '
	. $documentId
	. ' "'
	. $filename
	. '" with '
	. count($chunks)
	. ' chunks'
	. PHP_EOL;