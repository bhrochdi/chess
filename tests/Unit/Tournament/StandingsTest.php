<?php

declare(strict_types=1);

namespace LeCercle\Tests\Unit\Tournament;

use LeCercle\Chess\Result;
use LeCercle\Tournament\Pairing;
use LeCercle\Tournament\Round;
use LeCercle\Tournament\StandingRow;
use LeCercle\Tournament\Standings;
use PHPUnit\Framework\TestCase;

final class StandingsTest extends TestCase
{
    /**
     * @param list<StandingRow> $rows
     * @return list<string>
     */
    private static function ids(array $rows): array
    {
        return array_map(static fn(StandingRow $r): string => $r->playerId, $rows);
    }

    public function testScoring(): void
    {
        $rounds = [
            new Round(1, [
                new Pairing(1, 'A', 'B', Result::WhiteWins),
                Pairing::bye(2, 'C'),
            ]),
            new Round(2, [
                new Pairing(1, 'C', 'A', Result::Draw),
                Pairing::bye(2, 'B'),
            ]),
        ];

        $rows = (new Standings())->compute(
            ['A', 'B', 'C'],
            $rounds,
            ['A' => 'alice', 'B' => 'bob', 'C' => 'carol'],
        );

        self::assertSame(['A', 'C', 'B'], self::ids($rows));
        self::assertSame([3, 1, 2], [$rows[0]->halfPoints, $rows[0]->wins, $rows[0]->played]);
        self::assertSame([3, 1, 2], [$rows[1]->halfPoints, $rows[1]->wins, $rows[1]->played]);
        self::assertSame([2, 1, 2], [$rows[2]->halfPoints, $rows[2]->wins, $rows[2]->played]);
        self::assertSame(1.5, $rows[0]->points());
    }

    public function testUnplayedPairingsDoNotCount(): void
    {
        $rows = (new Standings())->compute(
            ['A', 'B'],
            [new Round(1, [new Pairing(1, 'A', 'B')])],
            ['A' => 'alice', 'B' => 'bob'],
        );

        foreach ($rows as $r) {
            self::assertSame(0, $r->halfPoints);
            self::assertSame(0, $r->played);
            self::assertSame(0, $r->wins);
        }
    }

    public function testTiebreakByWinsBeforePseudo(): void
    {
        $rounds = [
            new Round(1, [new Pairing(1, 'W', 'L', Result::WhiteWins), new Pairing(2, 'D1', 'D2', Result::Draw)]),
            new Round(2, [new Pairing(1, 'W', 'L', Result::BlackWins), new Pairing(2, 'D2', 'D1', Result::Draw)]),
        ];

        $rows = (new Standings())->compute(
            ['D1', 'D2', 'W', 'L'],
            $rounds,
            ['D1' => 'aaa', 'D2' => 'bbb', 'W' => 'zzz', 'L' => 'yyy'],
        );

        // W et L : 1 point, 1 victoire ; D1 et D2 : 1 point, 0 victoire.
        self::assertSame(['L', 'W', 'D1', 'D2'], self::ids($rows));
    }

    public function testPseudoTiebreakIsCaseInsensitive(): void
    {
        $rows = (new Standings())->compute(['B', 'A'], [], ['B' => 'Bob', 'A' => 'alice']);

        self::assertSame(['A', 'B'], self::ids($rows));
    }

    public function testUnknownPlayersAreIgnored(): void
    {
        $rows = (new Standings())->compute(
            ['A'],
            [new Round(1, [new Pairing(1, 'A', 'Z', Result::WhiteWins)])],
            ['A' => 'alice'],
        );

        self::assertSame(['A'], self::ids($rows));
        // La rencontre est écartée en entier, comme calculerClassement : A n'est pas crédité.
        self::assertSame(0, $rows[0]->halfPoints);
        self::assertSame(0, $rows[0]->played);
    }

    public function testBlackWinCreditsBlack(): void
    {
        $rows = (new Standings())->compute(
            ['A', 'B'],
            [new Round(1, [new Pairing(1, 'A', 'B', Result::BlackWins)])],
            ['A' => 'alice', 'B' => 'bob'],
        );

        self::assertSame(['B', 'A'], self::ids($rows));
        self::assertSame([2, 1, 1], [$rows[0]->halfPoints, $rows[0]->wins, $rows[0]->played]);
        self::assertSame([0, 0, 1], [$rows[1]->halfPoints, $rows[1]->wins, $rows[1]->played]);
    }

    public function testByeWithoutResultDoesNotCount(): void
    {
        $rows = (new Standings())->compute(['A'], [new Round(1, [new Pairing(1, 'A', null)])], ['A' => 'alice']);

        self::assertSame([0, 0, 0], [$rows[0]->halfPoints, $rows[0]->wins, $rows[0]->played]);
    }

    public function testMissingPseudoFallsBackToId(): void
    {
        $rows = (new Standings())->compute(['b', 'a'], [], []);

        self::assertSame(['a', 'b'], self::ids($rows));
    }
}
