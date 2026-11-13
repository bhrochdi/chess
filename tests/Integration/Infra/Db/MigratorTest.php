<?php

declare(strict_types=1);

namespace LeCercle\Tests\Integration\Infra\Db;

use LeCercle\Infra\Db\Migrator;
use LeCercle\Tests\Integration\DatabaseTestCase;
use LeCercle\Tests\Integration\TestDatabase;
use PDO;
use RuntimeException;

final class MigratorTest extends DatabaseTestCase
{
    private const FIXTURES = __DIR__ . '/fixtures';
    private const TABLE = 'schema_migrations_it';

    protected function setUp(): void
    {
        parent::setUp();
        $pdo = self::pdo();
        foreach (self::column("SHOW TABLES LIKE 'it\\_fixture\\_%'") as $table) {
            $pdo->exec("DROP TABLE `$table`");
        }
        $pdo->exec('DROP TABLE IF EXISTS ' . self::TABLE);
    }

    public function testAppliesInOrderThenIsIdempotent(): void
    {
        $migrator = new Migrator(self::pdo(), self::FIXTURES . '/migrations-ok', self::TABLE);

        self::assertSame(['001-create', '002-insert'], $migrator->migrate());
        self::assertSame([], $migrator->migrate());
        self::assertSame([1], self::column('SELECT COUNT(*) FROM it_fixture_a'));
    }

    public function testFailedMigrationIsNotRecordedAndStopsTheRun(): void
    {
        $migrator = new Migrator(self::pdo(), self::FIXTURES . '/migrations-failing', self::TABLE);

        try {
            $migrator->migrate();
            self::fail('La migration 002 aurait dû échouer.');
        } catch (RuntimeException $e) {
            self::assertStringContainsString('Migration 002-broken en échec', $e->getMessage());
            self::assertInstanceOf(\PDOException::class, $e->getPrevious());
        }

        self::assertSame(['001-ok'], self::column('SELECT name FROM ' . self::TABLE . ' ORDER BY name'));
        self::assertSame([], self::column("SHOW TABLES LIKE 'it\\_fixture\\_c'"));
    }

    public function testRejectsAnInvalidTableName(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new Migrator(self::pdo(), self::FIXTURES . '/migrations-ok', 'migrations; DROP TABLE users');
    }

    public function testCliExitsOneOnFailure(): void
    {
        [$code, , $stderr] = self::runCli(['DB_PASS' => 'mot-de-passe-faux']);

        self::assertSame(1, $code);
        self::assertStringContainsString('Access denied', $stderr);
    }

    public function testCliReportsAnUpToDateDatabase(): void
    {
        TestDatabase::pdo();
        [$code, $stdout] = self::runCli([]);

        self::assertSame(0, $code);
        self::assertSame("Base à jour.\n", $stdout);
    }

    /**
     * @param array<string, string> $overrides
     *
     * @return array{int, string, string} code de sortie, sortie standard, sortie d'erreur
     */
    private static function runCli(array $overrides): array
    {
        $env = array_merge(getenv(), ['DB_NAME' => TestDatabase::config()->name], $overrides);
        $process = proc_open(
            [PHP_BINARY, \dirname(__DIR__, 4) . '/bin/migrate.php'],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            null,
            $env,
        );
        self::assertIsResource($process);
        $stdout = (string) stream_get_contents($pipes[1]);
        $stderr = (string) stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        return [proc_close($process), $stdout, $stderr];
    }

    /** @return list<mixed> */
    private static function column(string $sql): array
    {
        $statement = self::pdo()->query($sql);
        self::assertNotFalse($statement);

        return array_values($statement->fetchAll(PDO::FETCH_COLUMN));
    }
}
