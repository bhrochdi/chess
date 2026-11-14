<?php

declare(strict_types=1);

namespace LeCercle\Tests\Unit\Chess;

use LeCercle\Chess\Color;
use PHPUnit\Framework\TestCase;

final class ColorTest extends TestCase
{
    public function testOpponent(): void
    {
        self::assertSame(Color::Black, Color::White->opponent());
        self::assertSame(Color::White, Color::Black->opponent());
    }

    public function testFenLetters(): void
    {
        self::assertSame(Color::White, Color::from('w'));
        self::assertSame(Color::Black, Color::from('b'));
    }
}
