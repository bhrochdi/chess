<?php

declare(strict_types=1);

namespace LeCercle\Tests\Unit\User;

use LeCercle\User\Stats;
use PHPUnit\Framework\TestCase;

final class StatsTest extends TestCase
{
    public function testRoundTrip(): void
    {
        $data = ['w' => 2, 'l' => 1, 'd' => 0];
        self::assertSame($data, Stats::fromArray($data)->toArray());
    }

    public function testNonIntegerOrMissingValuesBecomeZero(): void
    {
        self::assertSame(['w' => 0, 'l' => 3, 'd' => 0], Stats::fromArray(['w' => 'x', 'l' => 3])->toArray());
    }

    public function testDefaultsAreZero(): void
    {
        self::assertSame(['w' => 0, 'l' => 0, 'd' => 0], (new Stats())->toArray());
    }
}
