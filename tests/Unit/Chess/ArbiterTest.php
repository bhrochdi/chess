<?php

declare(strict_types=1);

namespace LeCercle\Tests\Unit\Chess;

use LeCercle\Chess\Arbiter;
use LeCercle\Chess\AttackDetector;
use LeCercle\Chess\EndReason;
use LeCercle\Chess\Move;
use LeCercle\Chess\MoveGenerator;
use LeCercle\Chess\Outcome;
use LeCercle\Chess\Position;
use LeCercle\Chess\Result;
use LeCercle\Chess\Square;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ArbiterTest extends TestCase
{
    private Arbiter $arbiter;

    protected function setUp(): void
    {
        $this->arbiter = new Arbiter(new MoveGenerator(new AttackDetector()));
    }

    /**
     * Joue les coups (UCI sans promotion) et renvoie la position finale et les clés de répétition.
     *
     * @param list<string> $uciMoves
     *
     * @return array{Position, list<string>}
     */
    private static function play(string $fen, array $uciMoves): array
    {
        $p = Position::fromFen($fen);
        $keys = [$p->repetitionKey()];
        foreach ($uciMoves as $uci) {
            $p = $p->apply(new Move(
                Square::fromAlgebraic(substr($uci, 0, 2)),
                Square::fromAlgebraic(substr($uci, 2, 2)),
            ));
            $keys[] = $p->repetitionKey();
        }

        return [$p, $keys];
    }

    private function outcomeOf(string $fen): ?Outcome
    {
        $p = Position::fromFen($fen);

        return $this->arbiter->outcome($p, [$p->repetitionKey()]);
    }

    public function testScholarsMate(): void
    {
        [$p, $keys] = self::play(Fen::START, ['e2e4', 'e7e5', 'f1c4', 'b8c6', 'd1h5', 'g8f6', 'h5f7']);

        self::assertEquals(new Outcome(Result::WhiteWins, EndReason::Checkmate), $this->arbiter->outcome($p, $keys));
    }

    public function testFoolsMateWinsForBlack(): void
    {
        [$p, $keys] = self::play(Fen::START, ['f2f3', 'e7e5', 'g2g4', 'd8h4']);

        self::assertEquals(new Outcome(Result::BlackWins, EndReason::Checkmate), $this->arbiter->outcome($p, $keys));
    }

    public function testStalemate(): void
    {
        self::assertEquals(
            new Outcome(Result::Draw, EndReason::Stalemate),
            $this->outcomeOf('7k/5Q2/6K1/8/8/8/8/8 b - - 0 1'),
        );
    }

    public function testFiftyMoves(): void
    {
        self::assertEquals(
            new Outcome(Result::Draw, EndReason::FiftyMoves),
            $this->outcomeOf('4k3/8/8/8/8/8/8/R3K3 w - - 100 80'),
        );
    }

    public function testThreefold(): void
    {
        $cycle = ['g1f3', 'g8f6', 'f3g1', 'f6g8'];
        [$p, $keys] = self::play(Fen::START, [...$cycle, ...$cycle]);
        self::assertEquals(new Outcome(Result::Draw, EndReason::Threefold), $this->arbiter->outcome($p, $keys));

        [$p, $keys] = self::play(Fen::START, $cycle);
        self::assertNull($this->arbiter->outcome($p, $keys));
    }

    #[DataProvider('insufficientMaterialProvider')]
    public function testInsufficientMaterial(string $fen): void
    {
        self::assertEquals(
            new Outcome(Result::Draw, EndReason::InsufficientMaterial),
            $this->outcomeOf($fen),
        );
    }

    /** @return iterable<string, array{string}> */
    public static function insufficientMaterialProvider(): iterable
    {
        yield 'roi contre roi' => ['4k3/8/8/8/8/8/8/4K3 w - - 0 1'];
        yield 'roi et cavalier' => ['4k3/8/8/8/8/8/8/2N1K3 w - - 0 1'];
        yield 'roi et fou' => ['4k3/8/8/8/8/8/8/2B1K3 w - - 0 1'];
    }

    public function testSufficientMaterial(): void
    {
        self::assertNull($this->outcomeOf('4k3/8/8/8/8/8/8/R3K3 w - - 0 1'));
    }

    public function testMateTakesPrecedenceOverFiftyMoves(): void
    {
        self::assertEquals(
            new Outcome(Result::WhiteWins, EndReason::Checkmate),
            $this->outcomeOf('R5k1/5ppp/8/8/8/8/8/6K1 b - - 100 80'),
        );
    }
}
