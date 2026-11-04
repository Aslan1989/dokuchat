<?php

declare(strict_types=1);

namespace DokuChat;

use RuntimeException;
use JsonException;

final class LlmClient
{
	public function __construct(
		private readonly string $baseUrl,
		private readonly string $model,
		private readonly string $apiKey,
		private readonly int $timeoutSeconds = 50,
	) {

	}
	public static function fromConfig(Config $config): self
	{
		return new self(
			rtrim($config->get('LLM_BASE_URL'), '/'),
			$config->get('LLM_MODEL'),
			$config->get('LLM_API_KEY'),
			$config->getInt('LLM_TIMEOUT', 50),
		);
	}
	/**
	 * @param list<array{role: string, content: string}> $messages
	 */
	public function chat(array $messages): string
	{
		$payload = json_encode([
			'model' => $this->model,
			'messages' => $messages,
			'temperature' => 0,
		], JSON_THROW_ON_ERROR);

		$ch = curl_init($this->baseUrl . '/chat/completions');
		curl_setopt_array($ch, [
			CURLOPT_POST => true,
			CURLOPT_POSTFIELDS => $payload,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_HTTPHEADER => [
				'Content-type: application/json',
				'Authorization: Bearer ' . $this->apiKey,
			],
			CURLOPT_CONNECTTIMEOUT => 5,
			CURLOPT_TIMEOUT => $this->timeoutSeconds,
		]);
		$body = curl_exec($ch);
		if($body === false) {
			throw new RuntimeException('LLM request failed: ' . curl_error($ch));
		}
		$status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
		if ($status !== 200) {
			throw new RuntimeException("LLM returned HTTP $status: " . substr($body, 0, 200));
		}
		return self::extractContent($body);
	}
	/**
	 * Takes the raw JSON body of a /chat/completions response
	 * and returns the text of the answer.
	 */
	public static function extractContent(string $json): string
	{
		try {
			$data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
		} catch (JsonException $e) {
			throw new RuntimeException('LLM response is not valid JSON', 0, $e);
		}
		$finishReason = $data['choices'][0]['finish_reason'] ?? null;
		if ($finishReason === 'length') {
			throw new RuntimeException('LLM response was truncated');
		}
		$content = $data['choices'][0]['message']['content'] ?? null;
		if (!is_string($content)) {
			throw new RuntimeException('LLM response has no answer text: ' . substr($json, 0, 200));
		}
		return $content;
	}
}
