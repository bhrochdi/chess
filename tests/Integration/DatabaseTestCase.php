<?php

declare(strict_types=1);

namespace LeCercle\Tests\Integration;

use PDO;
use PHPUnit\Framework\TestCase;

/**
 * Base des tests qui touchent MySQL : toutes les lignes sont effacées avant
 * chaque test (sauf les tables de suivi des migrations).
 */
abstract class DatabaseTestCase extends TestCase
{
    protected static function pdo(): PDO
    {
        return TestDatabase::pdo();
    }

    protected function setUp(): void
    {
        $pdo = self::pdo();
        $tables = $pdo->query(
            "SELECT table_name FROM information_schema.tables
             WHERE table_schema = DATABASE() AND table_type = 'BASE TABLE'",
        );
        self::assertNotFalse($tables);

        $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        foreach ($tables->fetchAll(PDO::FETCH_COLUMN) as $table) {
            if (\is_string($table) && !str_starts_with($table, 'schema_migrations')) {
                $pdo->exec("DELETE FROM `$table`");
            }
        }
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
    }
}
