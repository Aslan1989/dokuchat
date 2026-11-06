<?php

declare(strict_types=1);

use DokuChat\Config;
use DokuChat\Database;

require __DIR__ . '/../vendor/autoload.php';

$config = Config::fromEnvironment();
$pdo = Database::connect($config);
$statements = [
	'documents' => <<<'SQL'
		CREATE TABLE IF NOT EXISTS documents (
			id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
			filename VARCHAR(255) NOT NULL,
			created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
		SQL,

	'chunks' => <<<'SQL'
		CREATE TABLE IF NOT EXISTS chunks (
		id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
		document_id INT UNSIGNED NOT NULL,
		position INT UNSIGNED NOT NULL,
		content TEXT NOT NULL,
		CONSTRAINT fk_chunks_document
			FOREIGN KEY (document_id) REFERENCES documents (id)
			ON DELETE CASCADE,
		UNIQUE KEY uq_chunks_document_position (document_id, position)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
		SQL,
		'chunks.embedding' => <<<'SQL'
			ALTER TABLE chunks ADD COLUMN IF NOT EXISTS embedding JSON NULL
			SQL,
];
foreach ($statements as $table => $sql) {
	$pdo->exec($sql);

	echo 'OK: ' . $table . PHP_EOL;
}
