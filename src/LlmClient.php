<?php

declare(strict_types=1);

namespace DokuChat;

use RuntimeException;
use JsonException;

final class LlmClient
{
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
