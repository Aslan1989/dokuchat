<?php

declare(strinct_types=1);

use DokuChat\Config;
use PHPUnit\Framework\TestCase;

final class ConfigTest extends TestCase
{
	public function testReturnValue(): void
	{
		$config = new Config([
			'DB_HOST' => 'db',
		]);
		$result = $config->get('DB_HOST');
		$this->assertSame('db', $result);
	}
	public function testTrimWhiteSpaces(): void
	{
		$config = new Config([
			'DB_HOST' => '	<db>	',
		]);
		$result = $config->get('DB_HOST');
		$this->assertSame('<db>', $result);
	}
	public function testStoresZero(): void
	{
		$config = new Config([
			'DB_HOST' => '0',
		]);
		$result = $config->get('DB_HOST');
		$this->assertSame('0', $result);
	}
	public function testReturnDafaultIfNoKey(): void
	{
		$config = new Config([]);
		$result = $config->get('DB_HOST', 'localhost');
		$this->assertSame('localhost', $result);
	}
	public function testThrowsWhenTheKeyIsMissing(): void
	{
		$config = new Config([]);

		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage('DB_HOST');

		$config->get('DB_HOST');
	}
	public function testThrowExceptionIfOnlySpaces(): void
	{
		$config = new Config([
			'DB_HOST' => '		',
		]);
		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage('DB_HOST');

		$config->get('DB_HOST');
	}
}