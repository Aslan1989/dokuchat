<?php

declare(strict_types=1);

namespace DokuChat;

use RuntimeException;
use JsonException;
use LogicException;

use function PHPUnit\Framework\isFloat;

/**
 * Talks to a language model server over HTTP.
 *
 * It uses the "OpenAI-compatible API": a standard set of URLs and JSON
 * formats that many servers understand (Ollama, GWDG SAIA, OpenAI, ...).
 * Because of this, the same code works with a local Ollama server and with
 * a cloud service. Only the settings in .env change.
 *
 * It has two jobs:
 *  - chat():  send messages, get an answer text back   (POST /chat/completions)
 *  - embed(): send a text, get a vector (list of floats) (POST /embeddings)
 */
final class LlmClient
{
	/**
	 * Constructor promotion: each parameter becomes a private readonly
	 * property. "readonly" means it cannot change after the object is built.
	 */
	public function __construct(
		private readonly string $baseUrl,        // e.g. http://ollama:11434/v1
		private readonly string $model,          // chat model, e.g. llama3.2:3b
		private readonly string $embeddingModel, // embedding model, e.g. bge-m3
		private readonly string $apiKey,         // sent as "Authorization: Bearer ..."
		private readonly int $timeoutSeconds = 50,
	) {}

	/**
	 * Builds a client from the environment settings (.env).
	 *
	 * A "named constructor": the normal constructor stays simple and easy to
	 * use in tests, and this method knows where the real settings come from.
	 */
	public static function fromConfig(Config $config): self
	{
		return new self(
			// Remove a trailing "/" so that baseUrl . '/embeddings'
			// never becomes ".../v1//embeddings".
			rtrim($config->get('LLM_BASE_URL'), '/'),
			$config->get('LLM_MODEL'),
			$config->get('EMBEDDING_MODEL'),
			$config->get('LLM_API_KEY'),
			// 50 s is below nginx's 60 s limit. So PHP stops first and can
			// show a clear error, instead of nginx showing "504 Gateway Timeout".
			$config->getInt('LLM_TIMEOUT', 50),
		);
	}

	/**
	 * Sends a conversation to the chat model and returns its answer text.
	 *
	 * $messages is a list like:
	 *   [['role' => 'system', 'content' => 'rules...'],
	 *    ['role' => 'user',   'content' => 'sources + question']]
	 *
	 * @param list<array{role: string, content: string}> $messages
	 */
	public function chat(array $messages): string
	{
		$body = $this->postJson('/chat/completions', [
			'model' => $this->model,
			'messages' => $messages,
			// temperature 0 = the model always picks the most likely next
			// word. Answers become (almost) repeatable, which is important
			// for the eval: the same question should give the same result.
			'temperature' => 0,
		]);
		return self::extractContent($body);
	}

	/**
	 * Takes the raw JSON body of a /chat/completions response
	 * and returns the text of the answer.
	 *
	 * The response looks like:
	 *   {"choices": [{"message": {"content": "Die Antwort ..."},
	 *                 "finish_reason": "stop"}]}
	 *
	 * It is "static" and "public" so that tests can check the parsing with a
	 * fake JSON string, without a running model server.
	 */
	public static function extractContent(string $json): string
	{
		try {
			// true = decode into arrays, not objects.
			// 512 = maximum nesting depth (the default).
			// JSON_THROW_ON_ERROR = throw an exception on bad JSON instead of
			// silently returning null.
			$data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
		} catch (JsonException $e) {
			// Turn it into a RuntimeException: the web page catches
			// RuntimeException and shows "Sprachmodell nicht erreichbar" (502).
			// The original exception is kept as "previous" ($e) for debugging.
			throw new RuntimeException('LLM response is not valid JSON', 0, $e);
		}

		// "length" means the model stopped because it hit its token limit.
		// The answer is cut off, so we refuse it instead of showing half a
		// sentence to the user.
		$finishReason = $data['choices'][0]['finish_reason'] ?? null;
		if ($finishReason === 'length') {
			throw new RuntimeException('LLM response was truncated');
		}

		// "?? null" avoids a warning if any key on the path is missing.
		$content = $data['choices'][0]['message']['content'] ?? null;
		if (!is_string($content)) {
			// Only the first 200 characters go into the error, so a huge
			// response does not flood the log.
			throw new RuntimeException('LLM response has no answer text: ' . substr($json, 0, 200));
		}
		return $content;
	}

	/**
	 * Sends a Post request with a JSON body and returns the response body.
	 *
	 * Both chat() and embed() use this, so the HTTP details (headers,
	 * timeouts, error checks) live in one place.
	 *
	 * @param array<string, mixed> $payload
	 */
	private function postJson(string $endpoint, array $payload): string
	{
		$json = json_encode($payload, JSON_THROW_ON_ERROR);

		$ch = curl_init($this->baseUrl . $endpoint);
		curl_setopt_array($ch, [
			CURLOPT_POST => true,
			CURLOPT_POSTFIELDS => $json,
			// Return the body as a string instead of printing it.
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_HTTPHEADER => [
				'Content-type: application/json',
				// Ollama ignores the key; cloud services need it.
				'Authorization: Bearer ' . $this->apiKey,
			],
			// Max 5 s to open the connection: if the server is down,
			// we find out fast.
			CURLOPT_CONNECTTIMEOUT => 5,
			// Max time for the whole request. A model on a CPU can be slow,
			// so this is much longer.
			CURLOPT_TIMEOUT => $this->timeoutSeconds,
		]);
		$body = curl_exec($ch);

		// false = network problem (no connection, timeout, DNS error).
		if ($body === false) {
			throw new RuntimeException('LLM request failed: ' . curl_error($ch));
		}

		// The server answered, but maybe with an error (404 unknown model,
		// 401 wrong API key, 500 server error). Only 200 is a success.
		$status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
		if ($status !== 200) {
			throw new RuntimeException("LLM returned HTTP $status: " . substr($body, 0, 200));
		}
		return ($body);
	}

	/**
	 * Takes the raw JSON body of an /embeddings response and returns the
	 * vector as a list of floats.
	 *
	 * The response looks like:
	 *   {"data": [{"embedding": [0.012, -0.034, ...]}]}
	 *
	 * We check every value, because bad data here would only show up much
	 * later as a strange similarity score, which is hard to debug.
	 *
	 * @return list<float>
	 */
	public static function extractEmbedding(string $json): array
	{
		try {
			$data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
			$embedding = $data['data'][0]['embedding'] ?? null;
			if (!is_array($embedding) || $embedding === []) {
				throw new RuntimeException('Embedding is missing or empty');
			}
			$result = [];

			foreach ($embedding as $value) {
				if (!is_int($value) && !is_float($value)) {
					throw new RuntimeException('Embedding contains a non-numeric value');
				}

				// JSON has no separate int type, so "1" comes back as an int.
				// Convert every value to float, so the result is always
				// list<float> (strict_types would complain otherwise).
				$result[] = (float) $value;
			}
			return $result;
		} catch (JsonException $e) {
			// Only JSON errors are caught here. The RuntimeExceptions above
			// are not JsonExceptions, so they pass through unchanged.
			throw new RuntimeException('Invalid embedding JSON', 0, $e);
		}
	}

	/**
	 * Turns a text into a vector (an "embedding").
	 *
	 * An embedding is a long list of numbers (bge-m3: 1024 numbers) that
	 * describes the MEANING of the text. Texts with a similar meaning get
	 * vectors that point in a similar direction, even when they use
	 * different words. This is what makes semantic search possible.
	 *
	 * Important: the question and the chunks must be embedded with the SAME
	 * model, otherwise the vectors cannot be compared.
	 *
	 * @return list<float>
	 */
	public function embed(string $text): array
	{
		$body = $this->postJson('/embeddings', [
			'model' => $this->embeddingModel,
			'input' => $text,
		]);
		return self::extractEmbedding($body);
	}
}
