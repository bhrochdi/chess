<?php

declare(strict_types=1);

namespace LeCercle\Tests\Unit\Support;

use LeCercle\Tests\Support\InMemoryThrottle;
use PHPUnit\Framework\TestCase;

final class InMemoryThrottleTest extends TestCase
{
    private const T = 1_794_477_600_000;

    public function testAllowsUpToMaxEventsInTheWindow(): void
    {
        $throttle = new InMemoryThrottle();

        self::assertTrue($throttle->allow('a', 2, 1000, self::T));
        self::assertTrue($throttle->allow('a', 2, 1000, self::T + 1));
        self::assertFalse($throttle->allow('a', 2, 1000, self::T + 2));
    }

    public function testAnEventLeavesTheWindowAfterItsDuration(): void
    {
        $throttle = new InMemoryThrottle();
        $throttle->allow('a', 1, 1000, self::T);

        self::assertFalse($throttle->allow('a', 1, 1000, self::T + 999));
        self::assertTrue($throttle->allow('a', 1, 1000, self::T + 1000));
    }

    public function testARefusedEventIsNotCounted(): void
    {
        $throttle = new InMemoryThrottle();
        $throttle->allow('a', 1, 1000, self::T);
        $throttle->allow('a', 1, 1000, self::T + 900);

        self::assertTrue($throttle->allow('a', 1, 1000, self::T + 1000));
    }

    public function testKeysAreIndependent(): void
    {
        $throttle = new InMemoryThrottle();
        $throttle->allow('a', 1, 1000, self::T);

        self::assertTrue($throttle->allow('b', 1, 1000, self::T));
        self::assertSame(['a', 'b'], $throttle->keys());
    }
}
