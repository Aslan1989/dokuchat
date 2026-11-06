<?php

declare(strict_types=1);

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

while (true) {
	$chunks = $repository->findChunksWithoutEmbedding(20);

	if ($chunks === []) {
		break;
	}

	foreach ($chunks as $chunk) {
		$chunkId = (int) $chunk['id'];

		$embedding = $client->embed($chunk['content']);

		$repository->saveEmbedding($chunkId, $embedding);

		$count++;

		echo 'Embedded chunk ' . $chunkId . PHP_EOL;
	}
}

echo 'Embedded ' . $count . ' chunks' . PHP_EOL;
