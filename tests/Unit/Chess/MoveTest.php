<?php

declare(strict_types=1);

namespace LeCercle\Tests\Unit\Chess;

use LeCercle\Chess\Move;
use LeCercle\Chess\MoveFlag;
use LeCercle\Chess\PieceType;
use LeCercle\Chess\Square;
use PHPUnit\Framework\TestCase;

final class MoveTest extends TestCase
{
    public function testUciWithPromotion(): void
    {
        $move = new Move(Square::fromAlgebraic('e7'), Square::fromAlgebraic('e8'), MoveFlag::Normal, PieceType::Knight);
        self::assertSame('e7e8n', $move->toUci());
    }

    public function testUciWithoutPromotion(): void
    {
        $move = new Move(Square::fromAlgebraic('e2'), Square::fromAlgebraic('e4'), MoveFlag::DoublePush);
        self::assertSame('e2e4', $move->toUci());
    }

    public function testEquals(): void
    {
        $a = new Move(Square::fromAlgebraic('e2'), Square::fromAlgebraic('e4'), MoveFlag::DoublePush);
        $b = new Move(Square::fromAlgebraic('e2'), Square::fromAlgebraic('e4'), MoveFlag::DoublePush);
        $c = new Move(Square::fromAlgebraic('e2'), Square::fromAlgebraic('e3'));
        self::assertTrue($a->equals($b));
        self::assertFalse($a->equals($c));
    }
}
