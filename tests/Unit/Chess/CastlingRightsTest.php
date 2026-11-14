<?php

declare(strict_types=1);

namespace LeCercle\Tests\Unit\Chess;

use InvalidArgumentException;
use LeCercle\Chess\CastlingRights;
use LeCercle\Chess\Color;
use LeCercle\Chess\MoveFlag;
use LeCercle\Chess\Square;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CastlingRightsTest extends TestCase
{
    public function testFenRoundTrip(): void
    {
        self::assertSame('Kq', CastlingRights::fromFen('Kq')->toFen());
        self::assertSame('KQkq', CastlingRights::fromFen('KQkq')->toFen());
        self::assertSame('-', CastlingRights::fromFen('-')->toFen());
    }

    public function testWithoutColor(): void
    {
        $rights = CastlingRights::fromFen('KQkq')->withoutColor(Color::White);
        self::assertSame('kq', $rights->toFen());
        self::assertSame('-', $rights->withoutColor(Color::Black)->toFen());
    }

    public function testWithoutRookSquare(): void
    {
        $all = CastlingRights::fromFen('KQkq');
        self::assertSame('Qkq', $all->withoutRookSquare(Square::fromAlgebraic('h1'))->toFen());
        self::assertSame('Kkq', $all->withoutRookSquare(Square::fromAlgebraic('a1'))->toFen());
        self::assertSame('KQq', $all->withoutRookSquare(Square::fromAlgebraic('h8'))->toFen());
        self::assertSame('KQk', $all->withoutRookSquare(Square::fromAlgebraic('a8'))->toFen());
        self::assertSame('KQkq', $all->withoutRookSquare(Square::fromAlgebraic('e4'))->toFen());
    }

    public function testAllows(): void
    {
        $rights = CastlingRights::fromFen('Kq');
        self::assertTrue($rights->allows(Color::White, MoveFlag::CastleKingside));
        self::assertFalse($rights->allows(Color::White, MoveFlag::CastleQueenside));
        self::assertFalse($rights->allows(Color::Black, MoveFlag::CastleKingside));
        self::assertTrue($rights->allows(Color::Black, MoveFlag::CastleQueenside));
        self::assertFalse($rights->allows(Color::White, MoveFlag::Normal));
    }

    /** @return list<array{string}> */
    public static function invalidFen(): array
    {
        return [['KX'], ['KK'], [''], ["K\n"]];
    }

    #[DataProvider('invalidFen')]
    public function testInvalidCastlingFenThrows(string $fen): void
    {
        $this->expectException(InvalidArgumentException::class);
        CastlingRights::fromFen($fen);
    }
}
