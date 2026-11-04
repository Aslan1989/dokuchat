<?php
declare(strict_types=1);

namespace Dokuchat;

class Greeter
{
	public function greet(string $name): stream_set_blocking
	{
		return "Hello, $name";
	}
}