<?php

declare(strict_types=1);

namespace App\Services;

use PDO;
use PDOException;

class Database
{
    private static ?PDO $connection = null;

    public static function connection(): PDO
    {
        if (self::$connection instanceof PDO) {
            return self::$connection;
        }

        $dsn = trim((string) ($_ENV['DB_DSN'] ?? ''));
        $username = (string) ($_ENV['DB_USERNAME'] ?? '');
        $password = (string) ($_ENV['DB_PASSWORD'] ?? '');

        if ($dsn === '') {
            $dsn = self::buildDsnFromEnv();
        }

        if ($dsn === '') {
            throw new PDOException('Database DSN is not configured. Set DB_DSN in .env');
        }

        self::$connection = new PDO($dsn, $username, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);

        return self::$connection;
    }

    private static function buildDsnFromEnv(): string
    {
        $connection = strtolower(trim((string) ($_ENV['DB_CONNECTION'] ?? '')));
        if ($connection !== 'mysql') {
            return '';
        }

        $host = trim((string) ($_ENV['DB_HOST'] ?? '127.0.0.1'));
        $port = trim((string) ($_ENV['DB_PORT'] ?? '3306'));
        $database = trim((string) ($_ENV['DB_DATABASE'] ?? ''));
        $charset = trim((string) ($_ENV['DB_CHARSET'] ?? 'utf8mb4'));

        if ($database === '') {
            return '';
        }

        return sprintf('mysql:host=%s;port=%s;dbname=%s;charset=%s', $host, $port, $database, $charset);
    }
}
