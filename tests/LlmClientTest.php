<?php

declare(strict_types=1);

namespace DokuChat\Tests;

use DokuChat\LlmClient;
use PHPUnit\Framework\TestCase;

use RuntimeException;

final class LlmClientTest extends TestCase
{
	public function testExtractsContent(): void
	{
		$json = <<<'JSON'
		{"id":"chatcmpl-990","object":"chat.completion","created":1793825538,"model":"llama3.2:3b","system_fingerprint":"fp_ollama","choices":[{"index":0,"message":{"role":"assistant","content":"Hallo!"},"finish_reason":"stop"}],"usage":{"prompt_tokens":34,"prompt_tokens_details":{"cached_tokens":0},"completion_tokens":3,"total_tokens":37}}
		JSON;

		$result = LlmClient::extractContent($json);

		$this->assertSame('Hallo!', $result);
	}
	public function testIncorrectJson(): void
	{
		$json = '<html>502 Bad Gateway</html>';

		$this->expectException(RuntimeException::class);

		LlmClient::extractContent($json);
	}
	public function testNoFieldWithAnswer(): void
	{
		$json = '{"error":"model not found"}';

		$this->expectException(RuntimeException::class);

		LlmClient::extractContent($json);
	}
	public function testTruncatedAnswer(): void
	{
		$json = '{"choices":[{"message":{"content":"Hello"},"finish_reason":"length"}]}';

		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage('truncated');

		LlmClient::extractContent($json);
	}
}
