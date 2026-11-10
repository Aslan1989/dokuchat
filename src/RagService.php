<?php

declare(strict_types=1);

namespace DokuChat;

final class RagService
{
	public function __construct(
		private readonly LlmClient $client,
		private readonly DocumentRepository $repository,
		private readonly int $topK = 3,
	) {}

	public static function fromConfig(Config $config): self
	{
		$client = LlmClient::fromConfig($config);

		$pdo = Database::connect($config);
		$repository = new DocumentRepository($pdo);

		$topK = $config->getInt('RAG_TOP_K', 2);

		return new self($client, $repository, $topK);
	}

	/**
	 * @return array{answer: string, sources: list<array<string, mixed>>}
	 */
	public function answer(string $question): array
	{
		$chunks = $this->repository->findEmbeddedChunks();

		if ($chunks === []) {
			return [
				'answer' => 'Es sind noch keine Dokumente geladen.',
				'sources' => [],
			];
		}

		$queryVector = $this->client->embed($question);

		$sources = Retriever::rank(
			$queryVector,
			$chunks,
			$this->topK
		);

		$messages = PromptBuilder::build($question, $sources);

		$answer = $this->client->chat($messages);

		return [
			'answer' => $answer,
			'sources' => $sources,
		];
	}
}
