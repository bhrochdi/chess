<?php

declare(strict_types=1);

namespace LeCercle\Tests\Unit\Tournament;

use LeCercle\Chess\Result;
use LeCercle\Shared\ConflictException;
use LeCercle\Tournament\Elimination;
use LeCercle\Tournament\Pairing;
use LeCercle\Tournament\Round;
use PHPUnit\Framework\TestCase;

final class EliminationTest extends TestCase
{
    public function testBracketOrder(): void
    {
        self::assertSame([0, 7, 3, 4, 1, 6, 2, 5], Elimination::bracketOrder(8));
        self::assertSame([0, 3, 1, 2], Elimination::bracketOrder(4));
        self::assertSame([0, 1], Elimination::bracketOrder(2));
    }

    public function testEightPlayersNoBye(): void
    {
        $round = (new Elimination())->firstRound(['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H']);

        self::assertSame(1, $round->number);
        self::assertCount(4, $round->pairings);
        $placed = [];
        foreach ($round->pairings as $p) {
            self::assertNull($p->result);
            $placed[] = $p->whiteId;
            $placed[] = $p->blackId;
        }
        sort($placed);
        self::assertSame(['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H'], $placed);
        self::assertSame('A', $round->pairings[0]->whiteId);
    }

    public function testFivePlayersThreeByes(): void
    {
        $round = (new Elimination())->firstRound(['A', 'B', 'C', 'D', 'E']);

        self::assertCount(4, $round->pairings);
        $byes = array_values(array_filter($round->pairings, static fn(Pairing $p): bool => $p->isBye()));
        self::assertCount(3, $byes);
        $placed = [];
        foreach ($round->pairings as $p) {
            foreach ([$p->whiteId, $p->blackId] as $id) {
                if ($id !== null) {
                    $placed[] = $id;
                }
            }
        }
        sort($placed);
        self::assertSame(['A', 'B', 'C', 'D', 'E'], $placed);
        foreach ($byes as $b) {
            self::assertNotNull($b->winner());
            self::assertContains($b->winner(), $placed);
            self::assertNotNull($b->result);
        }
    }

    public function testLoneBlackByeIsPrefilledBlackWins(): void
    {
        // 3 joueurs : taille 4, ordre [0,3,1,2] → la tête de série 0 affronte un exempt (noir)
        $round = (new Elimination())->firstRound(['A', 'B', 'C']);
        $first = $round->pairings[0];

        self::assertSame('A', $first->whiteId);
        self::assertNull($first->blackId);
        self::assertSame(Result::WhiteWins, $first->result);
    }

    public function testNextRoundPairsWinners(): void
    {
        $round = new Round(1, [
            new Pairing(1, 'A', 'D', Result::WhiteWins),
            new Pairing(2, 'B', 'C', Result::BlackWins),
        ]);

        $next = (new Elimination())->nextRound($round);

        self::assertNotNull($next);
        self::assertSame(2, $next->number);
        self::assertCount(1, $next->pairings);
        self::assertSame('A', $next->pairings[0]->whiteId);
        self::assertSame('C', $next->pairings[0]->blackId);
        self::assertNull($next->pairings[0]->result);
    }

    public function testFinalHasNoNextRound(): void
    {
        $round = new Round(2, [new Pairing(1, 'A', 'C', Result::BlackWins)]);

        self::assertNull((new Elimination())->nextRound($round));
    }

    public function testDrawBlocksTheBracket(): void
    {
        $round = new Round(1, [
            new Pairing(1, 'A', 'D', Result::Draw),
            new Pairing(2, 'B', 'C', Result::WhiteWins),
        ]);

        $this->expectException(ConflictException::class);
        $this->expectExceptionMessage('Élimination : un match est nul. Saisis un résultat décisif (départage) avant de continuer.');
        (new Elimination())->nextRound($round);
    }
}
