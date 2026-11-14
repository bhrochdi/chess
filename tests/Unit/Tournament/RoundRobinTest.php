<?php

declare(strict_types=1);

namespace LeCercle\Tests\Unit\Tournament;

use LeCercle\Tournament\Round;
use LeCercle\Tournament\RoundRobin;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class RoundRobinTest extends TestCase
{
    /**
     * @param list<Round> $rounds
     * @return list<list<array{string, string}>>
     */
    private static function colors(array $rounds): array
    {
        return array_map(
            static fn(Round $r): array => array_map(
                static fn($p): array => [(string) $p->whiteId, (string) $p->blackId],
                $r->pairings,
            ),
            $rounds,
        );
    }

    public function testFourPlayersExactSchedule(): void
    {
        self::assertSame(
            [[['A', 'D'], ['B', 'C']], [['C', 'A'], ['B', 'D']], [['A', 'B'], ['C', 'D']]],
            self::colors((new RoundRobin())->rounds(['A', 'B', 'C', 'D'])),
        );
    }

    public function testFiveMatchesTheJsSchedule(): void
    {
        self::assertSame(
            [
                [['B', 'E'], ['C', 'D']],
                [['E', 'A'], ['C', 'B']],
                [['A', 'D'], ['E', 'C']],
                [['C', 'A'], ['B', 'D']],
                [['A', 'B'], ['D', 'E']],
            ],
            self::colors((new RoundRobin())->rounds(['A', 'B', 'C', 'D', 'E'])),
        );
    }

    #[DataProvider('sizes')]
    public function testEveryPairMeetsExactlyOnce(int $n): void
    {
        $players = array_map(static fn(int $i): string => 'P' . $i, range(1, $n));
        $rounds = (new RoundRobin())->rounds($players);

        self::assertCount($n % 2 === 0 ? $n - 1 : $n, $rounds);

        $seen = [];
        $total = 0;
        foreach ($rounds as $index => $round) {
            self::assertSame($index + 1, $round->number);
            $inRound = [];
            $boards = [];
            foreach ($round->pairings as $p) {
                self::assertNotNull($p->whiteId);
                self::assertNotNull($p->blackId);
                $boards[] = $p->board;
                foreach ([$p->whiteId, $p->blackId] as $id) {
                    self::assertArrayNotHasKey($id, $inRound);
                    $inRound[$id] = true;
                }
                $pair = [$p->whiteId, $p->blackId];
                sort($pair);
                $key = implode('|', $pair);
                self::assertArrayNotHasKey($key, $seen);
                $seen[$key] = true;
                ++$total;
            }
            self::assertSame($boards === [] ? [] : range(1, count($boards)), $boards);
        }
        self::assertSame($n * ($n - 1) / 2, $total);
    }

    /** @return iterable<string, array{int}> */
    public static function sizes(): iterable
    {
        foreach (range(2, 10) as $n) {
            yield "$n joueurs" => [$n];
        }
    }

    public function testOnePlayerIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new RoundRobin())->rounds(['A']);
    }
}
