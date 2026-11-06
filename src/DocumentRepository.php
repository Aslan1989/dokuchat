<?php

declare(strict_types=1);

namespace DokuChat;

use PDO;
use Throwable;

final class DocumentRepository
{
	public function __construct(private readonly PDO $pdo) {}

	/**
	 * Saves a document and its chunks. All or nothing.
	 *
	 * @param list<string> $chunks
	 * @return int the new document id
	 */
	public function save(string $filename, array $chunks): int
	{
		$this->pdo->beginTransaction();

		try {
			$statement = $this->pdo->prepare(
				'INSERT INTO documents (filename) VALUES (:filename)'
			);

			$statement->execute([
				'filename' => $filename,
			]);

			$documentId = (int) $this->pdo->lastInsertId();

			$chunkStatement = $this->pdo->prepare(
				'INSERT INTO chunks (document_id, position, content)
				 VALUES (:document_id, :position, :content)'
			);
			
			foreach ($chunks as $position => $content) {
				$chunkStatement->execute([
					'document_id' => $documentId,
					'position' => $position,
					'content' => $content,
				]);
			}
			$this->pdo->commit();
			return $documentId;
		} catch (Throwable $e) {
			// if ($this->pdo->inTransaction()) {
			// 	$this->pdo->rollBack();
			// }
			throw $e;
		}
	}
}
