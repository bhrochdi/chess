<?php

declare(strict_types=1);

namespace LeCercle\Tests\Unit\Chess\Ai;

use LeCercle\Chess\Ai\PieceSquareEvaluator;
use LeCercle\Chess\Position;
use LeCercle\Tests\Unit\Chess\Fen;
use PHPUnit\Framework\TestCase;

final class PieceSquareEvaluatorTest extends TestCase
{
    public function testInitialPositionIsBalanced(): void
    {
        self::assertSame(0, (new PieceSquareEvaluator())->evaluate(Position::initial()));
    }

    public function testExtraQueenIsWorthMoreThanEightHundred(): void
    {
        $score = (new PieceSquareEvaluator())->evaluate(Position::fromFen('4k3/8/8/8/8/8/8/3QK3 w - - 0 1'));

        self::assertGreaterThan(800, $score);
    }

    public function testSymmetry(): void
    {
        $e = new PieceSquareEvaluator();

        foreach ([Fen::KIWIPETE, Fen::POS3, Fen::POS5] as $fen) {
            self::assertSame(
                $e->evaluate(Position::fromFen($fen)),
                -$e->evaluate(Position::fromFen(self::mirror($fen))),
                $fen,
            );
        }
    }

    /** Miroir de couleur : rangées inversées, casse des pièces échangée, camp au trait inversé. */
    private static function mirror(string $fen): string
    {
        [$board, $side, $castling, $ep, $half, $full] = explode(' ', $fen);
        $rows = array_reverse(explode('/', $board));
        $board = implode('/', array_map(static fn(string $r): string => self::swapCase($r), $rows));
        $side = $side === 'w' ? 'b' : 'w';
        $castling = $castling === '-' ? '-' : self::swapCase($castling);
        if ($ep !== '-') {
            $ep = $ep[0] . (string) (9 - (int) $ep[1]);
        }

        return implode(' ', [$board, $side, $castling, $ep, $half, $full]);
    }

    private static function swapCase(string $s): string
    {
        return strtr($s, 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ', 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz');
    }
}
