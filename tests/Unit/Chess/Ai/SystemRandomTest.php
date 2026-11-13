<?php

declare(strict_types=1);

namespace LeCercle\Tests\Unit\Chess\Ai;

use LeCercle\Chess\Ai\SystemRandom;
use PHPUnit\Framework\TestCase;

final class SystemRandomTest extends TestCase
{
    public function testMaximumDrawStaysBelowOne(): void
    {
        self::assertLessThan(1.0, SystemRandom::unit(2 ** 53 - 1));
        self::assertSame(0.0, SystemRandom::unit(0));
    }

    public function testFloatIsInHalfOpenUnitInterval(): void
    {
        $random = new SystemRandom();
        for ($i = 0; $i < 100; ++$i) {
            $value = $random->float();
            self::assertGreaterThanOrEqual(0.0, $value);
            self::assertLessThan(1.0, $value);
        }
    }
}
