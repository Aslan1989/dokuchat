<?php

declare(strict_types=1);

/**
 * Step 2 of loading documents: compute an embedding for every chunk that
 * does not have one yet, and store it in the database.
 *
 * Why separate from bin/ingest.php?
 *  - Embedding is slow (one HTTP call per chunk). If it fails in the middle,
 *    the saved chunks are not lost; just run this script again.
 *  - After changing the embedding model, only this step must run again.
 */

use DokuChat\Config;
use DokuChat\Database;
use DokuChat\DocumentRepository;
use DokuChat\LlmClient;

require __DIR__ . '/../vendor/autoload.php';

$config = Config::fromEnvironment();
$pdo = Database::connect($config);

$repository = new DocumentRepository($pdo);
$client = LlmClient::fromConfig($config);

$count = 0;

// Work in batches of 20 until no chunk without an embedding is left.
// Each saved chunk is no longer "embedding IS NULL", so the next query
// returns the next 20. This is why the loop ends, and why the script can be
// stopped and started again ("resumable").
while (true) {
	$chunks = $repository->findChunksWithoutEmbedding(20);

	if ($chunks === []) {
		break;
	}

	foreach ($chunks as $chunk) {
		$chunkId = (int) $chunk['id'];

		// Text -> vector (HTTP call to the embedding model).
		$embedding = $client->embed($chunk['content']);

		// Saved right away, one chunk at a time: if the next call fails,
		// the work done so far is kept.
		$repository->saveEmbedding($chunkId, $embedding);

		$count++;

		echo 'Embedded chunk ' . $chunkId . PHP_EOL;
	}
}

echo 'Embedded ' . $count . ' chunks' . PHP_EOL;
