<?php

declare(strict_types=1);

namespace LeCercle\Tests\Integration\Infra\Db;

use LeCercle\Infra\Db\Migrator;
use LeCercle\Tests\Integration\DatabaseTestCase;
use PDO;

final class InitialSchemaTest extends DatabaseTestCase
{
    private const ROOT = __DIR__ . '/../../../..';
    private const ADOPT_TABLE = 'schema_migrations_adopt';
    private const TEMP_DIR = __DIR__ . '/../../../../build/tests/initial-schema';

    protected function tearDown(): void
    {
        $pdo = self::pdo();
        $pdo->exec('DROP TABLE IF EXISTS ' . self::ADOPT_TABLE);
        // Laisse la base entièrement migrée, comme TestDatabase la fournit aux autres tests.
        self::dropDataTables();
        $pdo->exec('DELETE FROM schema_migrations');
        (new Migrator($pdo, self::ROOT . '/sql/migrations'))->migrate();
        if (is_dir(self::TEMP_DIR)) {
            array_map('unlink', glob(self::TEMP_DIR . '/*') ?: []);
            rmdir(self::TEMP_DIR);
        }
    }

    public function testTablesExistAfterMigration(): void
    {
        $tables = self::column('SHOW TABLES');

        foreach (['users', 'schema_migrations'] as $table) {
            self::assertContains($table, $tables);
        }
        self::assertContains('001-schema-initial', self::column('SELECT name FROM schema_migrations'));
    }

    public function testOldTablesAreGoneAfterAllMigrations(): void
    {
        $tables = self::column('SHOW TABLES');

        self::assertNotContains('kv_store', $tables);
        self::assertNotContains('stat_records', $tables);
    }

    public function testUnusedUserColumnsAreGoneAfterAllMigrations(): void
    {
        $columns = self::column("SELECT column_name FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'users'");

        foreach (['stats', 'legacy_salt', 'legacy_hash'] as $column) {
            self::assertNotContains($column, $columns);
        }
    }

    public function testInitialMigrationAdoptsExistingSchema(): void
    {
        // Seule 001 est rejouée : les migrations suivantes n'ont pas à être ré-exécutables.
        if (!is_dir(self::TEMP_DIR)) {
            mkdir(self::TEMP_DIR, 0777, true);
        }
        copy(self::ROOT . '/sql/migrations/001-schema-initial.sql', self::TEMP_DIR . '/001-schema-initial.sql');
        $migrator = new Migrator(self::pdo(), self::TEMP_DIR, self::ADOPT_TABLE);

        self::assertSame(['001-schema-initial'], $migrator->migrate());
    }

    /** Supprime toutes les tables sauf celles de suivi des migrations. */
    private static function dropDataTables(): void
    {
        $pdo = self::pdo();
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        foreach (self::column("SELECT table_name FROM information_schema.tables WHERE table_schema = DATABASE() AND table_type = 'BASE TABLE'") as $table) {
            if (\is_string($table) && !str_starts_with($table, 'schema_migrations')) {
                $pdo->exec("DROP TABLE `$table`");
            }
        }
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
    }

    /** @return list<mixed> */
    private static function column(string $sql): array
    {
        $statement = self::pdo()->query($sql);
        self::assertNotFalse($statement);

        return array_values($statement->fetchAll(PDO::FETCH_COLUMN));
    }
}
