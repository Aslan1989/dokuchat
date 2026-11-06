<?php

declare(strict_types=1);

use DokuChat\Config;
use DokuChat\Database;
use DokuChat\DocumentRepository;
use DokuChat\LlmClient;
use DokuChat\Retriever;

require __DIR__ . '/../vendor/autoload.php';

if ($argc !== 2) {
	fwrite(STDERR, 'Usage: php bin/search.php <question>' . PHP_EOL);
	exit(1);
}

$question = $argv[1];

$config = Config::fromEnvironment();
$pdo = Database::connect($config);

$client = LlmClient::fromConfig($config);
$repository = new DocumentRepository($pdo);

$queryVector = $client->embed($question);
$chunks = $repository->findEmbeddedChunks();

$results = Retriever::rank($queryVector, $chunks, 3);

foreach ($results as $result) {
	printf(
		"%.3f  %s #%d  %s\n",
		$result['score'],
		$result['filename'],
		$result['position'],
		mb_substr($result['content'], 0, 80, 'UTF-8')
	);
}
