<?php

declare(strict_types=1);

namespace LeCercle\Tests\Integration\Infra;

use LeCercle\Infra\Admin\PdoAdminJournal;
use LeCercle\Infra\Db\PdoTransactionRunner;
use LeCercle\Infra\Infrastructure;
use LeCercle\Infra\Session\PhpSession;
use LeCercle\Infra\SystemClock;
use LeCercle\Infra\User\PdoMembers;
use LeCercle\Tests\Integration\TestDatabase;
use PHPUnit\Framework\Attributes\BackupGlobals;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[BackupGlobals(false)]
final class InfrastructureTest extends TestCase
{
    private string|false $savedHost = false;

    protected function setUp(): void
    {
        $this->savedHost = getenv('DB_HOST');
    }

    protected function tearDown(): void
    {
        putenv($this->savedHost === false ? 'DB_HOST' : 'DB_HOST=' . $this->savedHost);
    }

    public function testBuildsEachServiceOnceAndLazily(): void
    {
        $infra = new Infrastructure(static fn() => TestDatabase::config());

        self::assertSame($infra->pdo(), $infra->pdo());
        self::assertInstanceOf(SystemClock::class, $infra->clock());
        self::assertSame($infra->clock(), $infra->clock());
        self::assertInstanceOf(PhpSession::class, $infra->session());
        self::assertInstanceOf(PdoTransactionRunner::class, $infra->transactions());
        self::assertSame($infra->transactions(), $infra->transactions());
    }

    public function testBuildsSharedContracts(): void
    {
        $infra = new Infrastructure(static fn() => TestDatabase::config());

        self::assertInstanceOf(PdoMembers::class, $infra->members());
        self::assertSame($infra->members(), $infra->members());
        self::assertNull($infra->access()->viewer());
        self::assertSame($infra->access(), $infra->access());
        self::assertInstanceOf(PdoAdminJournal::class, $infra->adminJournal());
        self::assertSame($infra->adminJournal(), $infra->adminJournal());
    }

    public function testMissingConfigurationFailsOnFirstPdoNotOnConstruction(): void
    {
        putenv('DB_HOST');
        $infra = Infrastructure::fromGlobals('/nonexistent');

        $this->expectException(RuntimeException::class);
        $infra->pdo();
    }
}
