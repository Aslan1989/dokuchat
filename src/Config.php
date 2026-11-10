<?php
declare(strict_types=1);

namespace DokuChat;

use RuntimeException;

/**
 * Reads settings (database, model URL, API key, ...).
 *
 * All settings come from environment variables, which Docker Compose fills
 * from .env. So no passwords are in the code or in Git, and the same code
 * runs with other settings without any change ("12-factor app" idea).
 */
final class Config
{
	/**
	 * The values are passed in as an array. In tests we can pass a small
	 * array by hand, without touching the real environment.
	 *
	 * @param array<string, string> $values
	 */
	public function __construct(private readonly array $values)
	{

	}

	/**
	 * Builds a Config from the real environment.
	 * getenv() without arguments returns all environment variables.
	 */
	public static function fromEnvironment(): self
	{
		return new self(getenv());
	}

	/**
	 * Returns a setting as a string.
	 *
	 * Order: real value -> default -> exception.
	 * "Fail fast": a missing setting stops the program right away with a
	 * clear message, instead of failing later with a confusing error.
	 */
	public function get(string $key, ?string $default = null): string
	{
		// trim(): " " or an empty line in .env counts as "not set".
		$value = trim($this->values[$key] ?? '');
		if ($value !== '') {
			return $value;
		}
		if ($default !== null) {
			return $default;
		}
		throw new RuntimeException("Missing configuration: $key");
	}

	/**
	 * Returns a setting as an integer, e.g. DB_PORT or RAG_TOP_K.
	 * It reuses get() and then checks that the text is really a whole number.
	 */
	public function getInt(string $key, ?int $default = null): int
	{
		// get() works with strings, so the int default becomes a string.
		$stringDefault = $default === null ? null : (string) $default;
		$value = $this->get($key, $stringDefault);

		// FILTER_VALIDATE_INT accepts only whole numbers ("2" yes,
		// "2.5" or "abc" no). (int) "abc" would silently give 0.
		$number = filter_var($value, FILTER_VALIDATE_INT);
		if ($number === false) {
			throw new RuntimeException("Invalid integer for configuration $key: '$value'");
		}
		return $number;
	}
}
