<?php

declare(strict_types=1);

namespace LeCercle\Tests\Unit\Support;

use LeCercle\Tests\Support\FixedClock;
use PHPUnit\Framework\TestCase;

final class FixedClockTest extends TestCase
{
    public function testAdvanceMsMovesTheClock(): void
    {
        $c = new FixedClock(new \DateTimeImmutable('2026-11-12 10:00:00.000', new \DateTimeZone('UTC')));
        $c->advanceMs(1500);
        self::assertSame('2026-11-12 10:00:01.500', $c->now()->format('Y-m-d H:i:s.v'));
    }
}
