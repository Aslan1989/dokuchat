<?php
declare(strict_types=1);

namespace DokuChat\Tests;

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
	public function testRemovesSpacesAroundTheName() : void
	{
		$greeter = new Greeter();

		$result = $greeter->greet(' Aslan ');

		$this->assertSame('Hello, Aslan', $result);

	}
	public function testGreetsStrangerWhenNameIsEmpty() : void
	{
		$greeter = new Greeter();

		$result = $greeter->greet('');

		$this->assertSame('Hello, stranger', $result);

	}
	public function testGreetsStrangerWhenNameIsOnlySpaces() : void
	{
		$greeter = new Greeter();

		$result = $greeter->greet('		');

		$this->assertSame('Hello, stranger', $result);

	}
	public function testKeepsZeroAsAName() : void
	{
		$greeter = new Greeter();

		$result = $greeter->greet('0');

		$this->assertSame('Hello, 0', $result);

	}
}
