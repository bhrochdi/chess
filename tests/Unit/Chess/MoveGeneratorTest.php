<?php

declare(strict_types=1);

namespace LeCercle\Tests\Unit\Chess;

use LeCercle\Chess\AttackDetector;
use LeCercle\Chess\Move;
use LeCercle\Chess\MoveGenerator;
use LeCercle\Chess\Position;
use LeCercle\Chess\Square;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

final class MoveGeneratorTest extends TestCase
{
    private MoveGenerator $generator;

    protected function setUp(): void
    {
        $this->generator = new MoveGenerator(new AttackDetector());
    }

    /**
     * @param list<Move> $moves
     *
     * @return list<string>
     */
    private static function uci(array $moves): array
    {
        return array_map(static fn(Move $m): string => $m->toUci(), $moves);
    }

    #[DataProvider('perftCases')]
    public function testPerft(string $fen, int $depth, int $nodes): void
    {
        self::assertSame($nodes, Perft::count($this->generator, Position::fromFen($fen), $depth));
    }

    /** @return iterable<string, array{string, int, int}> */
    public static function perftCases(): iterable
    {
        yield 'start d1' => [Fen::START, 1, 20];
        yield 'start d2' => [Fen::START, 2, 400];
        yield 'start d3' => [Fen::START, 3, 8902];
        yield 'kiwipete d1' => [Fen::KIWIPETE, 1, 48];
        yield 'kiwipete d2' => [Fen::KIWIPETE, 2, 2039];
        yield 'pos3 d1' => [Fen::POS3, 1, 14];
        yield 'pos3 d2' => [Fen::POS3, 2, 191];
        yield 'pos3 d3' => [Fen::POS3, 3, 2812];
        yield 'pos4 d1' => [Fen::POS4, 1, 6];
        yield 'pos4 d2' => [Fen::POS4, 2, 264];
        yield 'pos5 d1' => [Fen::POS5, 1, 44];
        yield 'pos5 d2' => [Fen::POS5, 2, 1486];
    }

    #[Group('slow')]
    #[DataProvider('deepPerftCases')]
    public function testDeepPerft(string $fen, int $depth, int $nodes): void
    {
        self::assertSame($nodes, Perft::count($this->generator, Position::fromFen($fen), $depth));
    }

    /** @return iterable<string, array{string, int, int}> */
    public static function deepPerftCases(): iterable
    {
        yield 'start d4' => [Fen::START, 4, 197281];
        yield 'kiwipete d3' => [Fen::KIWIPETE, 3, 97862];
        yield 'pos3 d4' => [Fen::POS3, 4, 43238];
        yield 'pos4 d3' => [Fen::POS4, 3, 9467];
        yield 'pos5 d3' => [Fen::POS5, 3, 62379];
    }

    public function testPromotionGivesFourMoves(): void
    {
        $p = Position::fromFen('4k3/P7/8/8/8/8/8/4K3 w - - 0 1');
        $moves = self::uci($this->generator->legalMovesFrom($p, Square::fromAlgebraic('a7')));
        sort($moves);
        self::assertSame(['a7a8b', 'a7a8n', 'a7a8q', 'a7a8r'], $moves);
    }

    public function testEnPassantIllegalWhenItExposesKing(): void
    {
        $p = Position::fromFen('8/8/8/KPp4r/8/8/8/7k w - c6 0 2');
        self::assertNotContains('b5c6', self::uci($this->generator->legalMoves($p)));
    }

    public function testPinnedRookMovesOnlyAlongThePin(): void
    {
        // La tour clouée sur la colonne e ne quitte pas la colonne.
        $p = Position::fromFen('4k3/4r3/8/8/8/8/4R3/4K3 w - - 0 1');
        $moves = self::uci($this->generator->legalMovesFrom($p, Square::fromAlgebraic('e2')));
        sort($moves);
        self::assertSame(['e2e3', 'e2e4', 'e2e5', 'e2e6', 'e2e7'], $moves);
    }

    public function testPinnedKnightHasNoMove(): void
    {
        // Le cavalier cloué par le fou c3 n'a aucun coup.
        $p = Position::fromFen('4k3/8/8/8/8/2b5/3N4/4K3 w - - 0 1');
        self::assertSame([], $this->generator->legalMovesFrom($p, Square::fromAlgebraic('d2')));
    }

    public function testCannotCastleThroughAttackedSquare(): void
    {
        $moves = self::uci($this->generator->legalMoves(Position::fromFen('r3k2r/8/8/8/8/8/5r2/R3K2R w KQkq - 0 1')));
        self::assertNotContains('e1g1', $moves);
        self::assertContains('e1c1', $moves);
    }

    public function testCannotCastleOutOfCheck(): void
    {
        $moves = self::uci($this->generator->legalMoves(Position::fromFen('4k3/8/8/8/8/8/4r3/R3K2R w KQ - 0 1')));
        self::assertNotContains('e1g1', $moves);
        self::assertNotContains('e1c1', $moves);
    }

    public function testCapturingRookOnHomeSquareRemovesCastlingRight(): void
    {
        $p = Position::fromFen('r3k2r/1B6/8/8/8/8/8/4K3 w kq - 0 1');
        $capture = null;
        foreach ($this->generator->legalMovesFrom($p, Square::fromAlgebraic('b7')) as $m) {
            if ($m->toUci() === 'b7a8') {
                $capture = $m;
            }
        }
        self::assertNotNull($capture);
        self::assertSame('k', $p->apply($capture)->castling()->toFen());
    }

    public function testLegalMovesFromWrongSideIsEmpty(): void
    {
        self::assertSame([], $this->generator->legalMovesFrom(Position::initial(), Square::fromAlgebraic('e7')));
    }

    public function testIsInCheck(): void
    {
        self::assertTrue($this->generator->isInCheck(Position::fromFen('4k3/8/8/8/8/8/4r3/R3K2R w KQ - 0 1')));
        self::assertFalse($this->generator->isInCheck(Position::initial()));
    }
}
