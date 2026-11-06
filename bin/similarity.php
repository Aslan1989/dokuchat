<?php

declare(strict_types=1);

use DokuChat\Config;
use DokuChat\LlmClient;
use DokuChat\VectorMath;

require __DIR__ . '/../vendor/autoload.php';

if ($argc !== 3) {
	fwrite(STDERR, 'Usage: php bin/similarity.php <text1> <text2>' . PHP_EOL);
	exit(1);
}

$text1 = $argv[1];
$text2 = $argv[2];

$config = Config::fromEnvironment();
$client = LlmClient::fromConfig($config);

$vector1 = $client->embed($text1);
$vector2 = $client->embed($text2);

$similarity = VectorMath::cosineSimilarity($vector1, $vector2);

printf("%.3f" . PHP_EOL, $similarity);
