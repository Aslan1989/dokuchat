<?php

declare(strict_types=1);

namespace DokuChat;

use PDO;

final class Database
{
	/**
	 * Opens a connection to MariaDB with safe default settings.
	 *
	 * All settings come from the environment (.env), so there is no
	 * password in the code.
	 */
	public static function connect(Config $config): PDO
	{
		// DSN = "Data Source Name": one string that tells PDO which driver,
		// server, port and database to use.
		// charset=utf8mb4 = full UTF-8 (umlauts like ä, ö, ü and also emoji).
		// MySQL's old "utf8" can store only part of UTF-8.
		$dsn = sprintf(
			'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
			$config->get('DB_HOST'),
			$config->getInt('DB_PORT', 3306),
			$config->get('DB_NAME'),
		);

		return new PDO($dsn, $config->get('DB_USER'), $config->get('DB_PASSWORD'), [
			// Every SQL error throws a PDOException. Without this, errors
			// could be silently ignored.
			PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
			// Rows come back as ['id' => 1, 'filename' => '...'],
			// not also with numeric keys [0 => 1, 1 => '...'].
			PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
			// Use REAL prepared statements: the database receives the SQL
			// and the values separately. PDO does not glue them together
			// in PHP. This is the strongest protection against SQL injection.
			PDO::ATTR_EMULATE_PREPARES => false,
			// Give up connecting after 3 seconds, so /health answers fast
			// when the database is down.
			PDO::ATTR_TIMEOUT => 3,
		]);
	}
}
