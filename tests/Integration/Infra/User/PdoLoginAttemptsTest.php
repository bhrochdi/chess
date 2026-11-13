<?php

declare(strict_types=1);

namespace LeCercle\Tests\Integration\Infra\User;

use LeCercle\Infra\User\PdoLoginAttempts;
use LeCercle\Tests\Integration\DatabaseTestCase;

final class PdoLoginAttemptsTest extends DatabaseTestCase
{
    private const T = 1_794_477_600_000;

    public function testCountsFailuresInTheWindowPerKey(): void
    {
        $attempts = new PdoLoginAttempts(self::pdo());
        $attempts->record('id:a', self::T - 10);
        $attempts->record('id:a', self::T);
        $attempts->record('id:a', self::T + 5);
        $attempts->record('ip:b', self::T + 5);

        self::assertSame(2, $attempts->failuresSince('id:a', self::T));
        self::assertSame(1, $attempts->failuresSince('ip:b', self::T));
        self::assertSame(0, $attempts->failuresSince('id:z', 0));
    }

    public function testClearForgetsOneKeyOnly(): void
    {
        $attempts = new PdoLoginAttempts(self::pdo());
        $attempts->record('id:a', self::T);
        $attempts->record('ip:b', self::T);

        $attempts->clear('id:a');

        self::assertSame(0, $attempts->failuresSince('id:a', 0));
        self::assertSame(1, $attempts->failuresSince('ip:b', 0));
    }

    public function testRecordDeletesRowsOlderThanADay(): void
    {
        $attempts = new PdoLoginAttempts(self::pdo());
        $attempts->record('id:a', self::T - PdoLoginAttempts::KEEP_MS - 1);
        $attempts->record('id:a', self::T - PdoLoginAttempts::KEEP_MS);

        $attempts->record('ip:b', self::T);

        self::assertSame(1, $attempts->failuresSince('id:a', 0));
    }
}
