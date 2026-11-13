<?php

declare(strict_types=1);

namespace LeCercle\Tests\Integration\Infra\Db;

use LeCercle\Infra\Db\PdoFactory;
use LeCercle\Infra\Db\PdoTransactionRunner;
use LeCercle\Shared\ConflictException;
use LeCercle\Tests\Integration\DatabaseTestCase;
use LeCercle\Tests\Integration\TestDatabase;
use PDO;

final class PdoTransactionRunnerTest extends DatabaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        self::pdo()->exec('DROP TABLE IF EXISTS it_tx');
        self::pdo()->exec('CREATE TABLE it_tx (v INT NOT NULL) ENGINE=InnoDB');
    }

    protected function tearDown(): void
    {
        self::pdo()->exec('DROP TABLE IF EXISTS it_tx');
    }

    public function testCommitsAndReturnsTheResult(): void
    {
        $result = (new PdoTransactionRunner(self::pdo()))->run(function (): string {
            $this->insert(1);

            return 'r';
        });

        self::assertSame('r', $result);
        self::assertSame(1, self::rowCount(PdoFactory::create(TestDatabase::config())));
    }

    public function testRollsBackAndRethrows(): void
    {
        $exception = new ConflictException('x');
        $caught = null;

        try {
            (new PdoTransactionRunner(self::pdo()))->run(function () use ($exception): never {
                $this->insert(1);

                throw $exception;
            });
        } catch (ConflictException $e) {
            $caught = $e;
        }

        self::assertSame($exception, $caught);
        self::assertSame(0, self::rowCount(self::pdo()));
        self::assertFalse(self::pdo()->inTransaction());
    }

    public function testNestedRunJoinsTheOuterTransaction(): void
    {
        $runner = new PdoTransactionRunner(self::pdo());

        try {
            $runner->run(function () use ($runner): never {
                $this->insert(1);
                $runner->run(fn() => $this->insert(2));

                throw new ConflictException('x');
            });
        } catch (ConflictException) {
        }

        self::assertSame(0, self::rowCount(self::pdo()));
        self::assertFalse(self::pdo()->inTransaction());
    }

    private function insert(int $value): void
    {
        self::pdo()->prepare('INSERT INTO it_tx (v) VALUES (?)')->execute([$value]);
    }

    private static function rowCount(PDO $pdo): int
    {
        $statement = $pdo->query('SELECT COUNT(*) FROM it_tx');
        self::assertNotFalse($statement);

        return (int) $statement->fetchColumn();
    }
}
