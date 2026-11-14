<?php

declare(strict_types=1);

namespace LeCercle\Tests\Integration;

use PHPUnit\Framework\Attributes\BackupGlobals;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[BackupGlobals(false)]
final class TestDatabaseTest extends TestCase
{
    private const VARIABLES = ['DB_HOST', 'DB_TEST_NAME'];

    /** @var array<string, string|false> */
    private array $saved = [];

    protected function setUp(): void
    {
        foreach (self::VARIABLES as $name) {
            $this->saved[$name] = getenv($name);
        }
    }

    protected function tearDown(): void
    {
        foreach ($this->saved as $name => $value) {
            putenv($value === false ? $name : "$name=$value");
        }
    }

    public function testRefusesDatabaseNameWithoutTestSuffix(): void
    {
        putenv('DB_TEST_NAME=le_cercle');

        $this->expectException(RuntimeException::class);
        TestDatabase::config();
    }

    public function testRefusesMissingHost(): void
    {
        putenv('DB_HOST');

        $this->expectException(RuntimeException::class);
        TestDatabase::config();
    }

    public function testDefaultsToTheTestDatabase(): void
    {
        putenv('DB_TEST_NAME');

        self::assertSame('le_cercle_test', TestDatabase::config()->name);
    }
}
