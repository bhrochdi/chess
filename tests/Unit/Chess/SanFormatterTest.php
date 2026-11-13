<?php

declare(strict_types=1);

namespace LeCercle\Tests\Unit\Chess;

use LeCercle\Chess\AttackDetector;
use LeCercle\Chess\Move;
use LeCercle\Chess\MoveGenerator;
use LeCercle\Chess\Position;
use LeCercle\Chess\SanFormatter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SanFormatterTest extends TestCase
{
    private MoveGenerator $generator;
    private SanFormatter $san;

    protected function setUp(): void
    {
        $this->generator = new MoveGenerator(new AttackDetector());
        $this->san = new SanFormatter($this->generator);
    }

    /** Retrouve le coup légal portant cet UCI, pour avoir les bons drapeaux. */
    private function legal(Position $p, string $uci): Move
    {
        foreach ($this->generator->legalMoves($p) as $m) {
            if ($m->toUci() === $uci) {
                return $m;
            }
        }
        self::fail("Coup illégal : {$uci}");
    }

    /** @return iterable<string, array{string, string, string}> */
    public static function cases(): iterable
    {
        yield 'pion simple' => [Fen::START, 'e2e4', 'e4'];
        yield 'cavalier' => [Fen::START, 'g1f3', 'Nf3'];
        yield 'prise de pion' => ['rnbqkbnr/ppp1pppp/8/3p4/4P3/8/PPPP1PPP/RNBQKBNR w KQkq d6 0 2', 'e4d5', 'exd5'];
        yield 'petit roque' => ['r3k2r/8/8/8/8/8/8/R3K2R w KQkq - 0 1', 'e1g1', 'O-O'];
        yield 'grand roque' => ['r3k2r/8/8/8/8/8/8/R3K2R w KQkq - 0 1', 'e1c1', 'O-O-O'];
        yield 'promotion avec échec' => ['4k3/P7/8/8/8/8/8/4K3 w - - 0 1', 'a7a8q', 'a8=Q+'];
        yield 'sous-promotion' => ['4k3/P7/8/8/8/8/8/4K3 w - - 0 1', 'a7a8n', 'a8=N'];
        yield 'désambiguïsation par colonne' => ['4k3/8/8/8/8/8/8/1N1NK3 w - - 0 1', 'b1c3', 'Nbc3'];
        yield 'désambiguïsation par rangée' => ['4k3/8/8/8/R7/8/8/R3K3 w - - 0 1', 'a1a2', 'R1a2'];
        yield 'prise en passant' => ['4k3/8/8/4pP2/8/8/8/4K3 w - e6 0 2', 'f5e6', 'fxe6'];
        yield 'désambiguïsation complète' => ['4k3/8/8/8/Q2Q4/8/8/Q3K3 w - - 0 1', 'a4d1', 'Qa4d1'];
        yield 'prise avec désambiguïsation' => ['4k3/8/8/8/8/2n5/8/1N1NK3 w - - 0 1', 'b1c3', 'Nbxc3'];
        yield 'pièce clouée non ambiguë' => ['4r1k1/8/8/8/8/3N4/4N3/4K3 w - - 0 1', 'd3f4', 'Nf4'];
    }

    #[DataProvider('cases')]
    public function testFormat(string $fen, string $uci, string $expected): void
    {
        $p = Position::fromFen($fen);

        self::assertSame($expected, $this->san->format($p, $this->legal($p, $uci)));
    }

    public function testMateSuffix(): void
    {
        $p = Position::initial();
        foreach (['e2e4', 'e7e5', 'f1c4', 'b8c6', 'd1h5', 'g8f6'] as $uci) {
            $p = $p->apply($this->legal($p, $uci));
        }

        self::assertSame('Qxf7#', $this->san->format($p, $this->legal($p, 'h5f7')));
    }
}
