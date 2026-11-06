<?php

declare(strict_types=1);

namespace DokuChat\Tests;

use DokuChat\Config;
use DokuChat\Database;
use DokuChat\DocumentRepository;
use PDO;
use PDOException;
use PhpParser\Builder\Function_;
use PHPUnit\Framework\TestCase;

final class DocumentRepositoryTest extends TestCase
{
	private PDO $pdo;

	private DocumentRepository $repository;

	protected function setUp(): void
	{
		$this->pdo = Database::connect(Config::fromEnvironment());
		$this->repository = new DocumentRepository($this->pdo);
	}

	protected function tearDown(): void
	{
		$this->pdo->exec("DELETE FROM documents WHERE filename LIKE 'phpunit-%'");
	}

	public function testSavesNothingWhenOneChunkFails(): void
	{
		$documentsBefore = $this->countDocuments();
		$tooLong = str_repeat('a', 70000);

		try {
			$this->repository->save('phpunit-broken.txt', ['fine', $tooLong]);
			$this->fail('save() should have thrown an exception');
		} catch (PDOException) {
			// expected: the second chunk does not fit into TEXT
		}

		$this->assertSame($documentsBefore, $this->countDocuments());
	}

	private function countDocuments(): int
	{
		return (int) $this->pdo->query('SELECT COUNT(*) FROM documents')->fetchColumn();
	}

	public function testSavesTheDocumentWhitItsChunksInOrder(): void
	{
		$id = $this->repository->save(
			'phpunit-ok.txt',
			['first', 'second', 'third']
		);
		$this->assertGreaterThan(0, $id);

		$statement = $this->pdo->prepare(
			'SELECT position, content
			 FROM chunks
			 WHERE document_id = :id
			 ORDER BY position'
		);
		$statement->execute(['id' => $id]);

		$rows = $statement->fetchAll();
		$this->assertSame([
			['position' => 0, 'content' => 'first'],
			['position' => 1, 'content' => 'second'],
			['position' => 2, 'content' => 'third'],
		], $rows);
	}
}
