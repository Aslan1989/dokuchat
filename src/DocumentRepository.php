<?php

declare(strict_types=1);

namespace DokuChat;

use PDO;
use Throwable;
use RuntimeException;

/**
 * All SQL for documents and chunks lives in this class ("repository pattern").
 * The rest of the app calls methods like save() or findEmbeddedChunks() and
 * does not need to know any SQL.
 *
 * Tables (see bin/migrate.php):
 *   documents(id, filename, created_at)
 *   chunks(id, document_id, position, content, embedding JSON)
 *
 * Every query uses prepared statements with placeholders (:filename).
 * The values are sent separately from the SQL, so user input can never
 * change the SQL itself. This protects against SQL injection.
 */
final class DocumentRepository
{
	/**
	 * The PDO connection is passed in ("dependency injection").
	 * So tests can give it a connection to the test database.
	 */
	public function __construct(private readonly PDO $pdo) {}

	/**
	 * Saves a document and its chunks. All or nothing.
	 *
	 * It uses a TRANSACTION: either the document AND all its chunks are
	 * saved, or nothing is saved. Without a transaction, an error at
	 * chunk 5 would leave a half-saved document in the database.
	 *
	 * @param list<string> $chunks
	 * @return int the new document id
	 */
	public function save(string $filename, array $chunks): int
	{
		// From here on, changes are not final until commit().
		$this->pdo->beginTransaction();

		try {
			// 1. Insert the document row.
			$statement = $this->pdo->prepare(
				'INSERT INTO documents (filename) VALUES (:filename)'
			);

			$statement->execute([
				'filename' => $filename,
			]);

			// The AUTO_INCREMENT id that the database just gave the document.
			// The chunks need it as their foreign key.
			$documentId = (int) $this->pdo->lastInsertId();

			// 2. Insert the chunks. The statement is prepared ONCE and
			// executed many times with different values: faster, and the
			// SQL is parsed only once.
			$chunkStatement = $this->pdo->prepare(
				'INSERT INTO chunks (document_id, position, content)
				 VALUES (:document_id, :position, :content)'
			);

			// $position is the array index (0, 1, 2, ...) = the order of the
			// chunk in the document. Shown to users as "Abschnitt position+1".
			foreach ($chunks as $position => $content) {
				$chunkStatement->execute([
					'document_id' => $documentId,
					'position' => $position,
					'content' => $content,
				]);
			}

			// 3. Everything worked: make the changes final.
			$this->pdo->commit();
			return $documentId;
		} catch (Throwable $e) {
			// Something failed: undo everything since beginTransaction().
			// Throwable catches both exceptions and PHP errors.
			if ($this->pdo->inTransaction()) {
				$this->pdo->rollBack();
			}
			// Throw again, so the caller still sees the error.
			throw $e;
		}
	}

	/**
	 * Returns the id of a document with this filename, or null.
	 * bin/ingest.php uses it to refuse the same file twice: duplicate
	 * chunks would compete with each other during retrieval.
	 */
	public function findIdByFilename(string $filename): ?int
	{
		$statement = $this->pdo->prepare(
			'SELECT id FROM documents WHERE filename = :filename LIMIT 1'
		);
		$statement->execute(['filename' => $filename]);

		// fetchColumn() returns the first column of the first row,
		// or false if there is no row.
		$id = $statement->fetchColumn();

		return $id === false ? null : (int) $id;
	}

	/**
	 * Returns up to $limit chunks that have no embedding yet.
	 *
	 * Used by bin/embed-chunks.php. Working in small batches keeps memory
	 * low, and because only chunks with "embedding IS NULL" are returned,
	 * the script can be stopped and started again: it continues where it
	 * stopped ("resumable").
	 *
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
		// bindValue with PARAM_INT: LIMIT needs a number. Passed as a
		// string, it could become LIMIT '20', which is a SQL error.
		$statement->bindValue(':limit', $limit, PDO::PARAM_INT);
		$statement->execute();
		return $statement->fetchAll();
	}

	/**
	 * Stores the embedding vector of one chunk as a JSON array.
	 *
	 * Why JSON? MariaDB has no simple array type, and JSON is easy to write
	 * and read in PHP. (MariaDB 11.7+ has a real VECTOR type for big data.)
	 *
	 * @param list<float> $embedding
	 */
	public function saveEmbedding(int $chunkId, array $embedding): void
	{

		$json = json_encode(
			$embedding,
			JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION // Ohne diesen Flag wird 1.0 als 1 gespeichert. Beim Lesen bekomme ich dann einen int statt float.
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

		// Exactly one row must change. If not (e.g. wrong id), stop with an
		// error. This also protects bin/embed-chunks.php from an endless
		// loop: the chunk would stay NULL and be fetched again and again.
		if ($statement->rowCount() !== 1) {
			throw new RuntimeException("Chunk $chunkId was not updated");
		}
	}

	/**
	 * Returns ALL chunks that have an embedding, with the filename of their
	 * document and the vector already decoded into a PHP array.
	 *
	 * This is the input for the brute-force search in Retriever: every chunk
	 * is loaded and compared with the question. Fine for a few hundred
	 * chunks; for many thousands, a vector index in the database is better.
	 *
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
		// JOIN with documents to get the filename, which is shown as the
		// source of the answer.
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
			// Make sure the types are int, whatever the driver returns.
			// With strict_types, a wrong type would cause errors later.
			$row['id'] = (int) $row['id'];
			$row['position'] = (int) $row['position'];

			// The database gives the JSON back as a string.
			// Decode it into a PHP array of floats.
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
