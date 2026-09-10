<?php
/**
 * BaToPay PDO Database Connection (Singleton)
 */

declare(strict_types=1);

namespace App\Database;

use PDO;
use PDOException;
use App\Core\Bootstrap;

final class Connection
{
    private static ?PDO $pdo = null;

    public static function get(): PDO
    {
        if (self::$pdo === null) {
            self::connect();
        }
        return self::$pdo;
    }

    private static function connect(): void
    {
        $cfg = Bootstrap::config('db');
        if (!$cfg) {
            throw new \RuntimeException('Database configuration not found.');
        }

        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $cfg['host'],
            $cfg['port'] ?? 3306,
            $cfg['database'],
            $cfg['charset'] ?? 'utf8mb4'
        );

        try {
            self::$pdo = new PDO(
                $dsn,
                $cfg['username'],
                $cfg['password'],
                $cfg['options'] ?? [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                ]
            );
        } catch (PDOException $e) {
            throw new \RuntimeException('Database connection failed: ' . $e->getMessage(), 0, $e);
        }
    }

    public static function beginTransaction(): void
    {
        self::get()->beginTransaction();
    }

    public static function commit(): void
    {
        self::get()->commit();
    }

    public static function rollBack(): void
    {
        if (self::get()->inTransaction()) {
            self::get()->rollBack();
        }
    }

    private function __clone() {}
    public function __wakeup()
    {
        throw new \RuntimeException('Cannot unserialize singleton.');
    }
}
