<?php

declare(strict_types=1);

namespace LeCercle\Tests\Unit\Chess;

use InvalidArgumentException;
use LeCercle\Chess\Square;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SquareTest extends TestCase
{
    public function testIndexFromAlgebraic(): void
    {
        self::assertSame(28, Square::fromAlgebraic('e4')->index());
        self::assertSame(0, Square::fromAlgebraic('a1')->index());
        self::assertSame(63, Square::fromAlgebraic('h8')->index());
    }

    public function testAlgebraicFromIndex(): void
    {
        self::assertSame('h8', Square::fromIndex(63)->toAlgebraic());
        self::assertSame('a1', Square::fromIndex(0)->toAlgebraic());
    }

    public function testFileAndRank(): void
    {
        $square = Square::at(4, 3);
        self::assertSame(4, $square->file());
        self::assertSame(3, $square->rank());
        self::assertSame('e4', $square->toAlgebraic());
    }

    public function testOffsetOffBoardReturnsNull(): void
    {
        self::assertNull(Square::fromAlgebraic('h8')->offset(1, 0));
        self::assertNull(Square::fromAlgebraic('a1')->offset(0, -1));
    }

    public function testOffsetOnBoard(): void
    {
        self::assertSame('f6', Square::fromAlgebraic('e4')->offset(1, 2)?->toAlgebraic());
    }

    public function testSameSquareIsSameInstance(): void
    {
        self::assertSame(Square::at(4, 3), Square::fromAlgebraic('e4'));
        self::assertSame(Square::fromIndex(28), Square::fromAlgebraic('e3')->offset(0, 1));
    }

    /** @return list<array{string}> */
    public static function invalidAlgebraic(): array
    {
        return [['i1'], ['a9'], ['e'], [''], ['E4 '], ["e4\n"]];
    }

    #[DataProvider('invalidAlgebraic')]
    public function testInvalidAlgebraicThrows(string $text): void
    {
        $this->expectException(InvalidArgumentException::class);
        Square::fromAlgebraic($text);
    }

    public function testInvalidIndexThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Square::fromIndex(64);
    }

    public function testInvalidCoordinatesThrow(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Square::at(-1, 0);
    }
}
