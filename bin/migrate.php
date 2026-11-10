<?php

declare(strict_types=1);

use DokuChat\Config;
use DokuChat\Database;

require __DIR__ . '/../vendor/autoload.php';

/**
 * Creates the database tables. "Idempotent": safe to run many times,
 * because every statement uses IF NOT EXISTS. Existing data stays.
 *
 * Schema decisions:
 *  - InnoDB: supports transactions and foreign keys.
 *  - utf8mb4: full UTF-8 (umlauts, emoji).
 *  - FOREIGN KEY ... ON DELETE CASCADE: deleting a document deletes its
 *    chunks automatically, so no "orphan" chunks stay behind.
 *  - UNIQUE (document_id, position): a document cannot have two chunks at
 *    the same position.
 *  - embedding JSON NULL: NULL means "not embedded yet". It was added
 *    later with ALTER TABLE, which is why it is a separate step.
 */
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
