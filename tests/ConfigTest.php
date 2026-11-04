<?php

declare(strict_types=1);

use DokuChat\Config;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

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
	public function testGetIntReturnsNumber(): void
	{
		$config = new Config(['DB_PORT' => '3306']);

		$result = $config->getInt('DB_PORT');

		$this->assertSame(3306, $result);
	}
	public function testGetIntAcceptsZero(): void
	{
		$config = new Config(['RETRIES' => '0']);

		$result = $config->getInt('RETRIES');

		$this->assertSame(0, $result);
	}
	public function testGetIntReturnsTheDefaultWhenTheKeyIsMissing(): void
	{
		$config = new Config([]);

		$result = $config->getInt('DB_PORT', 3306);

		$this->assertSame(3306, $result);
	}
	public function testGetIntDoesNotHideAWrongValueBehindTheDefault(): void
	{
		$config = new Config(['DB_PORT' => 'abc']);

		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage('DB_PORT');
		$config->getInt('DB_PORT', 3306);
	}
	#[DataProvider('notWholeNumbers')]
	public function testGetIntThrowsForTextThatIsNotAWholeNumber(string $value): void
	{
		$config = new Config(['DB_PORT' => $value]);

		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage('DB_PORT');

		$config->getInt('DB_PORT');
	}

	/** @return array<string, array{string}> */
	public static function notWholeNumbers(): array
	{
		return [
			'letter inside' => ['33o6'],
			'decimal' => ['33.5'],
			'unit at the end' => ['80px'],
			'only letters' => ['abc'],
		];
	}
}