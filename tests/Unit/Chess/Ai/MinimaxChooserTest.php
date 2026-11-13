<?php

declare(strict_types=1);

namespace LeCercle\Tests\Unit\Chess\Ai;

use InvalidArgumentException;
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

final class MinimaxChooserTest extends TestCase
{
    private function chooser(int $depth): MinimaxChooser
    {
        return new MinimaxChooser(
            new MoveGenerator(new AttackDetector()),
            new PieceSquareEvaluator(),
            new FixedRandom(0.0),
            $depth,
            0,
        );
    }

    #[DataProvider('invalidSettings')]
    public function testInvalidSettingsThrow(int $depth, int $fuzz): void
    {
        $this->expectException(InvalidArgumentException::class);
        new MinimaxChooser(
            new MoveGenerator(new AttackDetector()),
            new PieceSquareEvaluator(),
            new FixedRandom(0.0),
            $depth,
            $fuzz,
        );
    }

    /** @return iterable<string, array{int, int}> */
    public static function invalidSettings(): iterable
    {
        yield 'profondeur nulle' => [0, 0];
        yield 'profondeur négative' => [-1, 0];
        yield 'bruit négatif' => [2, -1];
    }

    /** @return iterable<string, array{string, string}> */
    public static function bestMoves(): iterable
    {
        yield 'mat en un' => ['6k1/5ppp/8/8/8/8/8/R5K1 w - - 0 1', 'a1a8'];
        yield 'dame en prise' => ['4k3/8/8/3q4/8/8/8/3RK3 w - - 0 1', 'd1d5'];
        yield 'mat en un des Noirs' => ['3r2k1/8/8/8/8/8/5PPP/6K1 b - - 0 1', 'd8d1'];
    }

    #[DataProvider('bestMoves')]
    public function testFindsBestMove(string $fen, string $uci): void
    {
        $move = $this->chooser(2)->choose(Position::fromFen($fen));

        self::assertNotNull($move);
        self::assertSame($uci, $move->toUci());
    }

    public function testNoLegalMoveReturnsNull(): void
    {
        self::assertNull($this->chooser(2)->choose(Position::fromFen('7k/5Q2/6K1/8/8/8/8/8 b - - 0 1')));
    }

    public function testDeterministicWithFixedRandom(): void
    {
        $p = Position::fromFen(Fen::KIWIPETE);

        self::assertSame($this->chooser(2)->choose($p)?->toUci(), $this->chooser(2)->choose($p)?->toUci());
    }

    #[Group('slow')]
    public function testMasterLevelRespondsWithinBudget(): void
    {
        $chooser = AiLevel::Master->chooser(new MoveGenerator(new AttackDetector()), new FixedRandom(0.0));

        $start = hrtime(true);
        $move = $chooser->choose(Position::fromFen(Fen::KIWIPETE));
        $seconds = (hrtime(true) - $start) / 1e9;

        self::assertNotNull($move);
        // Cible ≤ 3 s ; l'assertion garde une marge pour les machines de CI plus lentes.
        self::assertLessThan(5.0, $seconds, sprintf('Niveau Maître sur Kiwipete : %.2f s', $seconds));
    }
}
