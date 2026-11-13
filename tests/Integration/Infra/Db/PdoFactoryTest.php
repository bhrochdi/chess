<?php

declare(strict_types=1);

namespace LeCercle\Tests\Integration\Infra\Db;

use LeCercle\Infra\Db\DbConfig;
use LeCercle\Infra\Db\PdoFactory;
use LeCercle\Tests\Integration\TestDatabase;
use PDO;
use PDOException;
use PHPUnit\Framework\TestCase;

final class PdoFactoryTest extends TestCase
{
    public function testConnectsToTheTestDatabaseInUtc(): void
    {
        $pdo = PdoFactory::create(TestDatabase::config());

        self::assertSame('le_cercle_test', self::scalar($pdo, 'SELECT DATABASE()'));
        self::assertSame('+00:00', self::scalar($pdo, 'SELECT @@session.time_zone'));
        // Le pilote MySQL renvoie 0 et non false.
        self::assertFalse((bool) $pdo->getAttribute(PDO::ATTR_EMULATE_PREPARES));
        self::assertSame(PDO::ERRMODE_EXCEPTION, $pdo->getAttribute(PDO::ATTR_ERRMODE));
        self::assertSame(PDO::FETCH_ASSOC, $pdo->getAttribute(PDO::ATTR_DEFAULT_FETCH_MODE));
    }

    public function testWrongPasswordThrowsPdoException(): void
    {
        $config = TestDatabase::config();

        $this->expectException(PDOException::class);
        PdoFactory::create(new DbConfig($config->host, $config->port, $config->name, $config->user, 'wrong'));
    }

    private static function scalar(PDO $pdo, string $sql): mixed
    {
        $statement = $pdo->query($sql);
        self::assertInstanceOf(\PDOStatement::class, $statement);

        return $statement->fetchColumn();
    }
}
