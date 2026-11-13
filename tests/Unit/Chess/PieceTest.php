<?php

declare(strict_types=1);

namespace LeCercle\Tests\Unit\Chess;

use InvalidArgumentException;
use LeCercle\Chess\Color;
use LeCercle\Chess\Piece;
use LeCercle\Chess\PieceType;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PieceTest extends TestCase
{
    public function testFromFenChar(): void
    {
        self::assertEquals(new Piece(PieceType::Knight, Color::Black), Piece::fromFenChar('n'));
        self::assertEquals(new Piece(PieceType::King, Color::White), Piece::fromFenChar('K'));
    }

    public function testToFenChar(): void
    {
        self::assertSame('Q', (new Piece(PieceType::Queen, Color::White))->toFenChar());
        self::assertSame('p', (new Piece(PieceType::Pawn, Color::Black))->toFenChar());
    }

    /** @return list<array{string}> */
    public static function invalidChars(): array
    {
        return [['x'], ['1'], ['']];
    }

    #[DataProvider('invalidChars')]
    public function testInvalidFenCharThrows(string $char): void
    {
        $this->expectException(InvalidArgumentException::class);
        Piece::fromFenChar($char);
    }
}
