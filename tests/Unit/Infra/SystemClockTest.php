<?php

declare(strict_types=1);

namespace LeCercle\Tests\Unit\Infra;

use LeCercle\Infra\SystemClock;
use PHPUnit\Framework\TestCase;

final class SystemClockTest extends TestCase
{
    public function testNowIsUtcAndNotInThePast(): void
    {
        $before = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $now = (new SystemClock())->now();
        self::assertSame('UTC', $now->getTimezone()->getName());
        self::assertGreaterThanOrEqual($before, $now);
    }
}
