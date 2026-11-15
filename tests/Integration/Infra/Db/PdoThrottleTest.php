<?php

declare(strict_types=1);

namespace LeCercle\Tests\Integration\Infra\Db;

use LeCercle\Infra\Db\PdoThrottle;
use LeCercle\Tests\Integration\DatabaseTestCase;

final class PdoThrottleTest extends DatabaseTestCase
{
    private const T = 1_794_477_600_000;

    /** @return list<array{string, int}> clé stockée et instant, par instant */
    private function rows(): array
    {
        $stmt = self::pdo()->query('SELECT hit_key, hit_at FROM throttle_hits ORDER BY hit_at, id');
        self::assertNotFalse($stmt);

        return array_values(array_map(static fn(array $r): array => [(string) $r['hit_key'], (int) $r['hit_at']], $stmt->fetchAll()));
    }

    public function testAllowsUpToMaxEventsInTheWindow(): void
    {
        $throttle = new PdoThrottle(self::pdo());

        self::assertTrue($throttle->allow('message:alice', 2, 1000, self::T));
        self::assertTrue($throttle->allow('message:alice', 2, 1000, self::T + 1));
        self::assertFalse($throttle->allow('message:alice', 2, 1000, self::T + 2));
    }

    public function testAnEventLeavesTheWindowAfterItsDuration(): void
    {
        $throttle = new PdoThrottle(self::pdo());
        $throttle->allow('message:alice', 1, 1000, self::T);

        self::assertFalse($throttle->allow('message:alice', 1, 1000, self::T + 999));
        self::assertTrue($throttle->allow('message:alice', 1, 1000, self::T + 1000));
    }

    public function testARefusedEventIsNotKept(): void
    {
        $throttle = new PdoThrottle(self::pdo());
        $throttle->allow('message:alice', 1, 1000, self::T);

        self::assertFalse($throttle->allow('message:alice', 1, 1000, self::T + 900));

        self::assertSame([self::T], array_column($this->rows(), 1));
    }

    public function testKeysAreIndependentAndStoredHashed(): void
    {
        $throttle = new PdoThrottle(self::pdo());
        $throttle->allow('guest:203.0.113.9', 1, 1000, self::T);

        self::assertTrue($throttle->allow('guest:198.51.100.7', 1, 1000, self::T));

        self::assertSame(
            [hash('sha256', 'guest:203.0.113.9'), hash('sha256', 'guest:198.51.100.7')],
            array_column($this->rows(), 0),
        );
    }

    public function testEachEventDeletesRowsOlderThanADay(): void
    {
        $throttle = new PdoThrottle(self::pdo());
        $throttle->allow('a', 5, 1000, self::T);
        $throttle->allow('b', 5, 1000, self::T + 1);

        $throttle->allow('c', 5, 1000, self::T + PdoThrottle::KEEP_MS + 1);

        self::assertSame([self::T + 1, self::T + PdoThrottle::KEEP_MS + 1], array_column($this->rows(), 1));
    }
}
