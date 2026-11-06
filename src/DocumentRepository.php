<?php

declare(strict_types=1);

namespace DokuChat;

use PDO;
use Throwable;
use RuntimeException;

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
			if ($this->pdo->inTransaction()) {
				$this->pdo->rollBack();
			}
			throw $e;
		}
	}
	/**
	 * @return list<array{id: int, content: string}>
	 */
	public function findChunksWithoutEmbedding(int $limit = 20): array
	{
		$statement = $this->pdo->prepare(
			'SELECT id, content
			 FROM chunks
			 WHERE embedding IS NULL
			 ORDER BY id
			 LIMIT :limit'
		);
		$statement->bindValue(':limit', $limit, PDO::PARAM_INT);
		$statement->execute();
		return $statement->fetchAll();
	}
	/**
	 * @param list<float> $embedding
	 */
	public function saveEmbedding(int $chunkId, array $embedding): void
	{

		$json = json_encode(
			$embedding,
			JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION
		);

		$statement = $this->pdo->prepare(
			'UPDATE chunks
			 SET embedding = :embedding
			 WHERE id = :id'
		);

		$statement->execute([
			'embedding' => $json,
			'id' => $chunkId,
		]);
		if ($statement->rowCount() !== 1) {
			throw new RuntimeException("Chunk $chunkId was not updated");
		}
	}
	/**
	 * @return list<array{
	 *     id: int,
	 *     filename: string,
	 *     position: int,
	 *     content: string,
	 *     embedding: array
	 * }>
	 */
	public function findEmbeddedChunks(): array
	{
		$statement = $this->pdo->query(
			'SELECT c.id, d.filename, c.position, c.content, c.embedding
         FROM chunks c
         JOIN documents d ON d.id = c.document_id
         WHERE c.embedding IS NOT NULL
         ORDER BY c.id'
		);

		$rows = $statement->fetchAll();
		$chunks = [];

		foreach ($rows as $row) {
			$row['id'] = (int) $row['id'];
			$row['position'] = (int) $row['position'];

			$row['embedding'] = json_decode(
				$row['embedding'],
				true,
				512,
				JSON_THROW_ON_ERROR
			);

			$chunks[] = $row;
		}

		return $chunks;
	}
}
