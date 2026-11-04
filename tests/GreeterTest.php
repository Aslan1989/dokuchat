<?php
declare(strict_types=1);

namespace DokuChat\tests;

use DokuChat\Greeter;
use PHPUnit\Framework\TestCase;

final class GreeterTest extends TestCase
{
	public function testGreetsByName() : void
	{
		$greeter = new Greeter();

		$result = $greeter->greet('Aslan');

		$this->assertSame('Hello, Aslan', $result);
	}
	public function testGreetsRemoveSpace() : void
	{
		$greeter = new Greeter();

		$result = $greeter->greet(' Aslan ');

		$this->assertSame('Hello, Aslan', $result);

	}
	public function testGreetsEmpty() : void
	{
		$greeter = new Greeter();

		$result = $greeter->greet('');

		$this->assertSame('Hello, stranger', $result);

	}
	public function testGreetsEmptySpaces() : void
	{
		$greeter = new Greeter();

		$result = $greeter->greet('		');

		$this->assertSame('Hello, stranger', $result);

	}
	public function testGreetsZeroValue() : void
	{
		$greeter = new Greeter();

		$result = $greeter->greet('0');

		$this->assertSame('Hello, 0', $result);

	}
}
