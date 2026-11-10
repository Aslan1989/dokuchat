<?php

declare(strict_types=1);

namespace DokuChat;

/**
 * Connects all RAG steps for one question:
 *   question -> embedding -> best chunks -> prompt -> language model -> answer
 *
 * The web page (public/index.php) and the evaluation (bin/eval.php) both use
 * this class. So the eval tests exactly the same code that users see.
 */
final class RagService
{
	public function __construct(
		private readonly LlmClient $client,
		private readonly DocumentRepository $repository,
		// k = how many chunks go into the prompt.
		private readonly int $topK = 3,
	) {}

	/**
	 * Builds the service with the real settings from .env.
	 */
	public static function fromConfig(Config $config): self
	{
		$client = LlmClient::fromConfig($config);

		$pdo = Database::connect($config);
		$repository = new DocumentRepository($pdo);

		// Default k = 2, chosen from the eval results: k=3 gave a small
		// model too much text and it answered worse. Can be changed per
		// run with RAG_TOP_K.
		$topK = $config->getInt('RAG_TOP_K', 2);

		return new self($client, $repository, $topK);
	}

	/**
	 * Answers one question from the stored documents.
	 *
	 * Returns the answer AND the sources, so the user can check where the
	 * answer comes from.
	 *
	 * @return array{answer: string, sources: list<array<string, mixed>>}
	 */
	public function answer(string $question): array
	{
		// 1. Load all chunks that already have an embedding.
		$chunks = $this->repository->findEmbeddedChunks();

		// No documents yet: answer directly. Calling the model without
		// sources would only invite it to invent an answer.
		if ($chunks === []) {
			return [
				'answer' => 'Es sind noch keine Dokumente geladen.',
				'sources' => [],
			];
		}

		// 2. Turn the question into a vector, with the same embedding
		// model that was used for the chunks.
		$queryVector = $this->client->embed($question);

		// 3. Retrieval: find the k chunks whose meaning is closest.
		$sources = Retriever::rank(
			$queryVector,
			$chunks,
			$this->topK
		);

		// 4. Augmentation: put the chunks into the prompt as numbered sources.
		$messages = PromptBuilder::build($question, $sources);

		// 5. Generation: the model writes the answer from those sources.
		$answer = $this->client->chat($messages);

		return [
			'answer' => $answer,
			'sources' => $sources,
		];
	}
}
