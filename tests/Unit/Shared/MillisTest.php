<?php

declare(strict_types=1);

namespace LeCercle\Tests\Unit\Shared;

use LeCercle\Shared\Millis;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class MillisTest extends TestCase
{
    public function testOf(): void
    {
        $t = new \DateTimeImmutable('2026-11-12 10:00:01.500', new \DateTimeZone('UTC'));

        self::assertSame(1794477601500, Millis::of($t));
    }

    public function testToDateTimeIsUtc(): void
    {
        self::assertSame('2026-11-12 10:00:01.500 UTC', Millis::toDateTime(1794477601500)->format('Y-m-d H:i:s.v e'));
    }

    /** @return iterable<string, array{int, string}> */
    public static function instants(): iterable
    {
        yield 'zero' => [0, '1970-01-01 00:00:00.000'];
        yield 'minus one' => [-1, '1969-12-31 23:59:59.999'];
        yield 'minus 500' => [-500, '1969-12-31 23:59:59.500'];
        yield 'minus 1500' => [-1500, '1969-12-31 23:59:58.500'];
        yield 'minus 2000' => [-2000, '1969-12-31 23:59:58.000'];
        yield 'positive' => [1794477601234, '2026-11-12 10:00:01.234'];
    }

    #[DataProvider('instants')]
    public function testToDateTimeIsExactAndRoundTrips(int $ms, string $expected): void
    {
        $t = Millis::toDateTime($ms);

        self::assertSame($expected, $t->format('Y-m-d H:i:s.v'));
        self::assertSame($ms, Millis::of($t));
    }
}
