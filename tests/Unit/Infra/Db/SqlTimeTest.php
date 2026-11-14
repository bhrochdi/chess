<?php

declare(strict_types=1);

namespace LeCercle\Tests\Unit\Infra\Db;

use LeCercle\Infra\Db\SqlTime;
use PHPUnit\Framework\TestCase;

final class SqlTimeTest extends TestCase
{
    public function testFormatConvertsToUtc(): void
    {
        $t = new \DateTimeImmutable('2026-11-12 11:00:00.250', new \DateTimeZone('Europe/Paris'));

        self::assertSame('2026-11-12 10:00:00.250', SqlTime::format($t));
    }

    public function testParseReadsMillisecondsAsUtc(): void
    {
        self::assertSame('2026-11-12 10:00:00.250 UTC', SqlTime::parse('2026-11-12 10:00:00.250')->format('Y-m-d H:i:s.v e'));
    }

    public function testParseWithoutFractionHasZeroMilliseconds(): void
    {
        self::assertSame('000', SqlTime::parse('2026-11-12 10:00:00')->format('v'));
    }

    public function testParseKeepsSignBeforeEpoch(): void
    {
        $t = SqlTime::parse('1969-12-31 23:59:59.500');

        self::assertSame('1969-12-31 23:59:59.500', SqlTime::format($t));
        self::assertLessThan(0, $t->getTimestamp());
    }

    public function testParseRejectsGarbage(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        SqlTime::parse('hier');
    }

    public function testParseRejectsOverflowingDate(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        SqlTime::parse('2026-13-45 10:00:00');
    }
}
