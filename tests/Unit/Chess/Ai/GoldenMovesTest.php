<?php

declare(strict_types=1);

namespace LeCercle\Tests\Unit\Chess\Ai;

use LeCercle\Chess\Ai\AiLevel;
use LeCercle\Chess\Ai\MinimaxChooser;
use LeCercle\Chess\Ai\PieceSquareEvaluator;
use LeCercle\Chess\AttackDetector;
use LeCercle\Chess\MoveGenerator;
use LeCercle\Chess\Position;
use LeCercle\Tests\Unit\Chess\Fen;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Valeurs de caractérisation relevées sur le moteur le 2026-11-12, avant toute
 * optimisation de vitesse. Elles figent le coup choisi par l'IA : ne jamais les
 * modifier pour faire passer un changement, c'est le changement qui est faux.
 */
final class GoldenMovesTest extends TestCase
{
    private const POS4_BLACK = 'r2q1rk1/pP1p2pp/Q4n2/bbp1p3/Np6/1B3NBn/pPPP1PPP/R3K2R b KQ - 0 1';
    private const EN_PASSANT = 'rnbqkbnr/ppp1p1pp/8/3pPp2/8/8/PPPP1PPP/RNBQKBNR w KQkq f6 0 3';
    private const SCHOLAR = 'r1bqkb1r/pppp1ppp/2n2n2/4p2Q/2B1P3/8/PPPP1PPP/RNB1K1NR w KQkq - 4 4';
    private const LONE_QUEEN = '8/8/8/4k3/8/8/8/3QK3 w - - 0 1';
    private const MIDDLEGAME_BLACK = 'r1bqk2r/pppp1ppp/2n2n2/2b1p3/2B1P3/3P1N2/PPP2PPP/RNBQK2R b KQkq - 0 5';

    private static function generator(): MoveGenerator
    {
        return new MoveGenerator(new AttackDetector());
    }

    private static function fuzzFree(int $depth): MinimaxChooser
    {
        return new MinimaxChooser(self::generator(), new PieceSquareEvaluator(), new FixedRandom(0.0), $depth, 0);
    }

    #[DataProvider('fuzzFreeCases')]
    public function testFuzzFreeMoveAtDepthTwoAndThree(string $fen, int $depth, string $uci): void
    {
        $move = self::fuzzFree($depth)->choose(Position::fromFen($fen));

        self::assertSame($uci, $move?->toUci());
    }

    #[Group('slow')]
    #[DataProvider('fuzzFreeDepthFourCases')]
    public function testFuzzFreeMoveAtDepthFour(string $fen, int $depth, string $uci): void
    {
        $move = self::fuzzFree($depth)->choose(Position::fromFen($fen));

        self::assertSame($uci, $move?->toUci());
    }

    #[DataProvider('levelCases')]
    public function testLevelMoveWithNoise(string $fen, string $level, string $uci): void
    {
        $chooser = AiLevel::from($level)->chooser(
            self::generator(),
            new SequenceRandom([0.9, 0.1, 0.5, 0.3, 0.7, 0.0, 0.99]),
        );

        self::assertSame($uci, $chooser->choose(Position::fromFen($fen))?->toUci());
    }

    /**
     * Nom => [FEN, d2, d3, d4, ai-2, ai-3].
     *
     * @return array<string, array{string, string, string, string, string, string}>
     */
    private static function table(): array
    {
        return [
            'départ' => [Fen::START, 'b1c3', 'b1c3', 'b1c3', 'b1c3', 'b1c3'],
            'kiwipete' => [Fen::KIWIPETE, 'e2a6', 'e2a6', 'e2a6', 'e2a6', 'e2a6'],
            'position 3' => [Fen::POS3, 'b4f4', 'b4f4', 'b4f4', 'b4f4', 'b4f4'],
            'position 4' => [Fen::POS4, 'd2d4', 'd2d4', 'g1h1', 'd2d4', 'd2d4'],
            'position 4 noirs' => [self::POS4_BLACK, 'd7d5', 'd7d5', 'g8h8', 'b5c4', 'd7d5'],
            'position 5' => [Fen::POS5, 'd7c8q', 'd7c8q', 'd7c8q', 'd7c8q', 'd7c8q'],
            'prise en passant' => [self::EN_PASSANT, 'b1c3', 'd1h5', 'b1c3', 'g1e2', 'd1h5'],
            'berger' => [self::SCHOLAR, 'h5f7', 'h5f7', 'h5f7', 'h5f7', 'h5f7'],
            'dame seule' => [self::LONE_QUEEN, 'd1d3', 'e1f1', 'd1d7', 'd1c2', 'e1f1'],
            'milieu noirs' => [self::MIDDLEGAME_BLACK, 'c5b4', 'c5b4', 'c5b4', 'c5b4', 'c5b4'],
        ];
    }

    /** @return iterable<string, array{string, int, string}> */
    public static function fuzzFreeCases(): iterable
    {
        foreach (self::table() as $name => [$fen, $d2, $d3]) {
            yield "$name d2" => [$fen, 2, $d2];
            yield "$name d3" => [$fen, 3, $d3];
        }
    }

    /** @return iterable<string, array{string, int, string}> */
    public static function fuzzFreeDepthFourCases(): iterable
    {
        foreach (self::table() as $name => [$fen, , , $d4]) {
            yield "$name d4" => [$fen, 4, $d4];
        }
    }

    /** @return iterable<string, array{string, string, string}> */
    public static function levelCases(): iterable
    {
        foreach (self::table() as $name => [$fen, , , , $ai2, $ai3]) {
            yield "$name ai-2" => [$fen, 'ai-2', $ai2];
            yield "$name ai-3" => [$fen, 'ai-3', $ai3];
        }
    }
}
