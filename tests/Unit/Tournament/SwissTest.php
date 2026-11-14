<?php

declare(strict_types=1);

namespace LeCercle\Tests\Unit\Tournament;

use LeCercle\Chess\Result;
use LeCercle\Tournament\Pairing;
use LeCercle\Tournament\Round;
use LeCercle\Tournament\StandingRow;
use LeCercle\Tournament\Standings;
use LeCercle\Tournament\Swiss;
use PHPUnit\Framework\TestCase;

final class SwissTest extends TestCase
{
    /**
     * @param list<string> $players
     * @param list<Round>  $rounds
     * @return list<StandingRow>
     */
    private function standings(array $players, array $rounds): array
    {
        return (new Standings())->compute($players, $rounds, array_combine($players, $players));
    }

    /** @return list<string> */
    private function placed(Round $round): array
    {
        $ids = [];
        foreach ($round->pairings as $p) {
            foreach ([$p->whiteId, $p->blackId] as $id) {
                if ($id !== null) {
                    $ids[] = $id;
                }
            }
        }

        return $ids;
    }

    public function testFirstRoundOfSix(): void
    {
        $players = ['A', 'B', 'C', 'D', 'E', 'F'];
        $round = (new Swiss())->nextRound([], $this->standings($players, []));

        self::assertSame(1, $round->number);
        self::assertCount(3, $round->pairings);
        self::assertCount(6, array_unique($this->placed($round)));
        self::assertSame([1, 2, 3], array_map(static fn(Pairing $p): int => $p->board, $round->pairings));
        // Appariement dans l'ordre du classement : le premier prend le suivant.
        self::assertSame('A', $round->pairings[0]->whiteId);
        self::assertSame('B', $round->pairings[0]->blackId);
    }

    public function testSecondRoundAvoidsRematchesAndPairsLeaders(): void
    {
        $players = ['A', 'B', 'C', 'D', 'E', 'F'];
        $r1 = new Round(1, [
            new Pairing(1, 'A', 'B', Result::WhiteWins),
            new Pairing(2, 'C', 'D', Result::WhiteWins),
            new Pairing(3, 'E', 'F', Result::Draw),
        ]);
        $round = (new Swiss())->nextRound([$r1], $this->standings($players, [$r1]));

        self::assertSame(2, $round->number);
        self::assertCount(3, $round->pairings);
        foreach ($round->pairings as $p) {
            $pair = [$p->whiteId, $p->blackId];
            sort($pair);
            self::assertNotContains($pair, [['A', 'B'], ['C', 'D'], ['E', 'F']]);
        }
        self::assertSame('A', $round->pairings[0]->whiteId);
        self::assertSame('C', $round->pairings[0]->blackId);
    }

    public function testOddCountGivesOneByeWorthAPoint(): void
    {
        $players = ['A', 'B', 'C', 'D', 'E'];
        $round = (new Swiss())->nextRound([], $this->standings($players, []));

        self::assertCount(3, $round->pairings);
        self::assertCount(5, array_unique($this->placed($round)));
        $byes = array_values(array_filter($round->pairings, static fn(Pairing $p): bool => $p->isBye()));
        self::assertCount(1, $byes);
        self::assertSame(Result::WhiteWins, $byes[0]->result);
        self::assertSame('E', $byes[0]->whiteId);
        // Le bye est ajouté en dernier.
        self::assertTrue($round->pairings[2]->isBye());
        self::assertSame(3, $round->pairings[2]->board);
    }

    public function testByeGoesToSomeoneWithoutAPreviousBye(): void
    {
        $r1 = new Round(1, [
            new Pairing(1, 'A', 'B', Result::WhiteWins),
            new Pairing(2, 'C', 'D', Result::WhiteWins),
            Pairing::bye(3, 'E'),
        ]);
        // Classement fait main : E, qui a déjà eu un bye, reste dernier (un bye vaut un point,
        // la situation est impossible avec Standings::compute).
        $standings = [
            new StandingRow('A', 2, 1, 1),
            new StandingRow('B', 2, 1, 1),
            new StandingRow('C', 0, 1, 0),
            new StandingRow('D', 0, 1, 0),
            new StandingRow('E', 0, 1, 0),
        ];
        $round = (new Swiss())->nextRound([$r1], $standings);

        self::assertSame(2, $round->number);
        $bye = $round->pairings[count($round->pairings) - 1];
        self::assertTrue($bye->isBye());
        self::assertSame('D', $bye->whiteId);
    }

    public function testByeFallsBackToLastWhenEveryoneHadOne(): void
    {
        $r1 = new Round(1, [Pairing::bye(1, 'A'), Pairing::bye(2, 'B'), Pairing::bye(3, 'C')]);
        $standings = [new StandingRow('A', 0, 0, 0), new StandingRow('B', 0, 0, 0), new StandingRow('C', 0, 0, 0)];
        $round = (new Swiss())->nextRound([$r1], $standings);

        $bye = $round->pairings[count($round->pairings) - 1];
        self::assertSame('C', $bye->whiteId);
    }

    public function testForcedRematchWhenNoOtherChoice(): void
    {
        $r1 = new Round(1, [new Pairing(1, 'A', 'B', Result::WhiteWins)]);
        $round = (new Swiss())->nextRound([$r1], $this->standings(['A', 'B'], [$r1]));

        self::assertSame(2, $round->number);
        self::assertCount(1, $round->pairings);
        self::assertSame('A', $round->pairings[0]->whiteId);
        self::assertSame('B', $round->pairings[0]->blackId);
    }
}
