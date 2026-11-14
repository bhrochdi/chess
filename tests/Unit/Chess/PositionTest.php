<?php

declare(strict_types=1);

namespace LeCercle\Tests\Unit\Chess;

use InvalidArgumentException;
use LeCercle\Chess\Color;
use LeCercle\Chess\Move;
use LeCercle\Chess\MoveFlag;
use LeCercle\Chess\Piece;
use LeCercle\Chess\PieceType;
use LeCercle\Chess\Position;
use LeCercle\Chess\Square;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PositionTest extends TestCase
{
    private static function sq(string $s): Square
    {
        return Square::fromAlgebraic($s);
    }

    public function testInitialPositionFen(): void
    {
        self::assertSame(Fen::START, Position::initial()->toFen());
    }

    #[DataProvider('fens')]
    public function testFenRoundTrip(string $fen): void
    {
        self::assertSame($fen, Position::fromFen($fen)->toFen());
    }

    /** @return array<string, array{string}> */
    public static function fens(): array
    {
        return [
            'départ' => [Fen::START],
            'kiwipete' => [Fen::KIWIPETE],
            'position 3' => [Fen::POS3],
            'position 5' => [Fen::POS5],
        ];
    }

    public function testAccessors(): void
    {
        $p = Position::fromFen(Fen::POS5);
        self::assertSame(Color::White, $p->sideToMove());
        self::assertSame('KQ', $p->castling()->toFen());
        self::assertNull($p->enPassant());
        self::assertSame(1, $p->halfmoveClock());
        self::assertSame(8, $p->fullmoveNumber());
        self::assertSame('e8', Position::initial()->kingSquare(Color::Black)?->toAlgebraic());
    }

    public function testKingSquareFollowsKingMoveAndCastling(): void
    {
        $castled = Position::fromFen('r3k2r/8/8/8/8/8/8/R3K2R w KQkq - 0 1')
            ->apply(new Move(self::sq('e1'), self::sq('g1'), MoveFlag::CastleKingside));
        self::assertSame('g1', $castled->kingSquare(Color::White)?->toAlgebraic());

        $moved = $castled->apply(new Move(self::sq('e8'), self::sq('d8')));
        self::assertSame('d8', $moved->kingSquare(Color::Black)?->toAlgebraic());
    }

    public function testKingSquareIsNullAfterTheKingIsCaptured(): void
    {
        // fromFen accepte encore un camp sans le trait en échec : apply peut alors prendre le roi.
        $p = Position::fromFen('4k3/8/8/8/8/8/4R3/4K3 w - - 0 1')->apply(new Move(self::sq('e2'), self::sq('e8')));

        self::assertNull($p->kingSquare(Color::Black));
        self::assertSame('e1', $p->kingSquare(Color::White)?->toAlgebraic());
    }

    public function testPieceAt(): void
    {
        $p = Position::initial();
        self::assertEquals(new Piece(PieceType::King, Color::Black), $p->pieceAt(self::sq('e8')));
        self::assertNull($p->pieceAt(self::sq('e4')));
        self::assertSame('e1', $p->kingSquare(Color::White)?->toAlgebraic());
    }

    public function testRepetitionKeyIsFirstFourFields(): void
    {
        self::assertSame('rnbqkbnr/pppppppp/8/8/8/8/PPPPPPPP/RNBQKBNR w KQkq -', Position::initial()->repetitionKey());
    }

    public function testApplyDoublePush(): void
    {
        $start = Position::initial();
        $p = $start->apply(new Move(self::sq('e2'), self::sq('e4'), MoveFlag::DoublePush));
        self::assertSame('rnbqkbnr/pppppppp/8/8/4P3/8/PPPP1PPP/RNBQKBNR b KQkq e3 0 1', $p->toFen());
        self::assertSame(Fen::START, $start->toFen());
    }

    public function testApplyIncrementsFullmoveAfterBlack(): void
    {
        $p = Position::initial()
            ->apply(new Move(self::sq('g1'), self::sq('f3')))
            ->apply(new Move(self::sq('g8'), self::sq('f6')));
        self::assertSame('rnbqkb1r/pppppppp/5n2/8/8/5N2/PPPPPPPP/RNBQKB1R w KQkq - 2 2', $p->toFen());
    }

    public function testApplyKingsideCastling(): void
    {
        $p = Position::fromFen('r3k2r/8/8/8/8/8/8/R3K2R w KQkq - 0 1')
            ->apply(new Move(self::sq('e1'), self::sq('g1'), MoveFlag::CastleKingside));
        self::assertSame('r3k2r/8/8/8/8/8/8/R4RK1 b kq - 1 1', $p->toFen());
    }

    public function testApplyQueensideCastlingBlack(): void
    {
        $p = Position::fromFen('r3k2r/8/8/8/8/8/8/R3K2R b KQkq - 0 1')
            ->apply(new Move(self::sq('e8'), self::sq('c8'), MoveFlag::CastleQueenside));
        self::assertSame('2kr3r/8/8/8/8/8/8/R3K2R w KQ - 1 2', $p->toFen());
    }

    public function testApplyEnPassant(): void
    {
        $p = Position::fromFen('4k3/8/8/3pP3/8/8/8/4K3 w - d6 0 2')
            ->apply(new Move(self::sq('e5'), self::sq('d6'), MoveFlag::EnPassant));
        self::assertSame('4k3/8/3P4/8/8/8/8/4K3 b - - 0 2', $p->toFen());
    }

    public function testApplyPromotion(): void
    {
        $p = Position::fromFen('4k3/P7/8/8/8/8/8/4K3 w - - 0 1')
            ->apply(new Move(self::sq('a7'), self::sq('a8'), MoveFlag::Normal, PieceType::Knight));
        self::assertSame('N3k3/8/8/8/8/8/8/4K3 b - - 0 1', $p->toFen());
    }

    public function testRookMoveClearsOneRight(): void
    {
        $p = Position::fromFen('r3k2r/8/8/8/8/8/8/R3K2R w KQkq - 0 1')
            ->apply(new Move(self::sq('h1'), self::sq('h5')));
        self::assertSame('Qkq', $p->castling()->toFen());
    }

    public function testCapturedRookClearsRight(): void
    {
        $p = Position::fromFen('r3k2r/8/8/8/8/8/6B1/R3K2R w KQkq - 0 1')
            ->apply(new Move(self::sq('g2'), self::sq('a8')));
        self::assertSame('KQk', $p->castling()->toFen());
        self::assertSame(0, $p->halfmoveClock());
    }

    public function testApplyFromEmptySquareThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Position::initial()->apply(new Move(self::sq('e4'), self::sq('e5')));
    }

    #[DataProvider('invalidFens')]
    public function testInvalidFenThrows(string $fen): void
    {
        $this->expectException(InvalidArgumentException::class);
        Position::fromFen($fen);
    }

    /** @return array<string, array{string}> */
    public static function invalidFens(): array
    {
        return [
            'vide' => [''],
            'texte' => ['not a fen'],
            '7 rangées' => ['8/8/8/8/8/8/8 w - - 0 1'],
            '9 colonnes' => ['rnbqkbnr/pppppppp/9/8/8/8/PPPPPPPP/RNBQKBNR w KQkq - 0 1'],
            'rangée trop courte' => ['rnbqkbnr/pppppppp/7/8/8/8/PPPPPPPP/RNBQKBNR w KQkq - 0 1'],
            'camp invalide' => ['rnbqkbnr/pppppppp/8/8/8/8/PPPPPPPP/RNBQKBNR x KQkq - 0 1'],
            'en passant invalide' => ['rnbqkbnr/pppppppp/8/8/8/8/PPPPPPPP/RNBQKBNR w KQkq e9 0 1'],
            'en passant mauvaise rangée' => ['rnbqkbnr/pppppppp/8/8/8/8/PPPPPPPP/RNBQKBNR w KQkq e4 0 1'],
            'en passant incohérent avec le trait' => ['4k3/3pn3/8/8/8/8/8/4K3 b - e6 0 1'],
            'en passant rangée 3 avec les blancs' => ['4k3/8/8/8/4P3/8/8/4K3 w - e3 0 1'],
            'deux rois blancs' => ['4k3/8/8/8/8/8/8/K3K3 w - - 0 1'],
            'sans roi noir' => ['8/8/8/8/8/8/8/4K3 w - - 0 1'],
            'demi-coups négatif' => ['rnbqkbnr/pppppppp/8/8/8/8/PPPPPPPP/RNBQKBNR w KQkq - -1 1'],
            'numéro de coup nul' => ['rnbqkbnr/pppppppp/8/8/8/8/PPPPPPPP/RNBQKBNR w KQkq - 0 0'],
            'saut de ligne final' => ["rnbqkbnr/pppppppp/8/8/8/8/PPPPPPPP/RNBQKBNR w KQkq - 0 1\n"],
            'champ en trop' => ['rnbqkbnr/pppppppp/8/8/8/8/PPPPPPPP/RNBQKBNR w KQkq - 0 1 x'],
            'pièce inconnue' => ['rnbqkbnx/pppppppp/8/8/8/8/PPPPPPPP/RNBQKBNR w KQkq - 0 1'],
        ];
    }
}
