<?php

declare(strict_types=1);

use DokuChat\Chunker;
use DokuChat\Config;
use DokuChat\Database;
use DokuChat\DocumentRepository;

require __DIR__ . '/../vendor/autoload.php';

/**
 * Step 1 of loading documents: read a text file, split it into chunks and
 * save them in the database. Embeddings come later (bin/embed-chunks.php).
 *
 * Usage: php bin/ingest.php data/museum.txt
 */

/**
 * Prints an error to STDERR and stops with exit code 1.
 * STDERR (not echo) so errors are separate from normal output, and exit
 * code 1 tells the shell / scripts that the command failed.
 * Return type "never" = this function never returns.
 */
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

// Broken UTF-8 would break the Chunker regex, the JSON for the model and
// the database. Better to stop here with a clear message.
if (!mb_check_encoding($text, 'UTF-8')) {
	fail('File is not valid UTF-8');
}

// Chunking happens BEFORE connecting to the database: if the file is
// empty, we stop without ever touching the database.
// Why chunks at all? A whole document is too long for one prompt, and one
// embedding for a whole document would mix many topics. Small chunks give
// precise search results.
$chunker = new Chunker();
$chunks = $chunker->chunk($text);

if ($chunks === []) {
	fail('File contains no text');
}

$config = Config::fromEnvironment();
$pdo = Database::connect($config);

$repository = new DocumentRepository($pdo);

// Only the filename ("museum.txt"), not the full path. It is shown to users
// as the source, and the path of the server should not be visible.
$filename = basename($path);

// Refuse a file that is already saved. Otherwise its chunks would exist
// twice, and the copies would take the top-k places from other chunks.
$existingId = $repository->findIdByFilename($filename);

if ($existingId !== null) {
	fail('"' . $filename . '" is already saved as document ' . $existingId . '. Delete it first.');
}

// Document + all chunks in one transaction (see DocumentRepository::save).
$documentId = $repository->save($filename, $chunks);

echo 'Saved document '
	. $documentId
	. ' "'
	. $filename
	. '" with '
	. count($chunks)
	. ' chunks'
	. PHP_EOL;