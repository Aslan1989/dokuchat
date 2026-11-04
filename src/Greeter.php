<?php
declare(strict_types=1);

namespace DokuChat;

final class Greeter
{
	public function greet(string $name): string
	{
		$name = trim($name);
		if($name === '') {
			return "Hello, stranger";
		}
		return "Hello, $name";
	}
}