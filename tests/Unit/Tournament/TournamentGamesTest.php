<?php

declare(strict_types=1);

namespace LeCercle\Tests\Unit\Tournament;

use LeCercle\Chess\Result;
use LeCercle\Tests\Unit\Tournament\TournamentServiceFixture as F;
use PHPUnit\Framework\TestCase;

final class TournamentGamesTest extends TestCase
{
    private F $fixture;

    protected function setUp(): void
    {
        $this->fixture = new F();
    }

    public function testStartCreatesOneGamePerPairingOfTheFirstRound(): void
    {
        $id = $this->fixture->started('Open', 'round-robin', [F::ALICE, F::BOB, F::CAROL]);

        $round = $this->fixture->as(F::ROOT)->get($id)['rounds'][0];

        self::assertCount(1, $this->fixture->games->created, 'trois joueurs : une seule rencontre jouée');
        self::assertSame(['g000000000001'], array_column($round['pairings'], 'gameId'));
        self::assertSame(600, $this->fixture->games->created[0]['tc']?->baseSeconds);
    }

    public function testSwissByeGetsNoGame(): void
    {
        $id = $this->fixture->started('Suisse', 'suisse', [F::ALICE, F::BOB, F::CAROL], 3);

        $pairings = $this->fixture->as(F::ROOT)->get($id)['rounds'][0]['pairings'];

        self::assertCount(1, $this->fixture->games->created);
        self::assertSame(['g000000000001', null], array_column($pairings, 'gameId'));
    }

    public function testNextRoundCreatesGamesForTheNewRoundOnly(): void
    {
        $id = $this->fixture->started('Open', 'round-robin', [F::ALICE, F::BOB, F::CAROL, F::DAVE]);
        self::assertCount(2, $this->fixture->games->created);

        $this->fixture->as(F::ROOT)->nextRound($id, true);

        self::assertCount(4, $this->fixture->games->created);
        $rounds = $this->fixture->as(F::ROOT)->get($id)['rounds'];
        self::assertSame(['g000000000003', 'g000000000004'], array_column($rounds[1]['pairings'], 'gameId'));
        self::assertSame([null, null], array_column($rounds[2]['pairings'], 'gameId'));
    }

    public function testGameResultLandsOnItsPairingEvenInAPastRound(): void
    {
        $id = $this->fixture->started('Open', 'round-robin', [F::ALICE, F::BOB, F::CAROL, F::DAVE]);
        $this->fixture->as(F::ROOT)->nextRound($id, true);
        $this->fixture->journal->entries = [];

        $this->fixture->service->recordGameResult('g000000000001', Result::BlackWins);

        $first = $this->fixture->as(F::ROOT)->get($id)['rounds'][0]['pairings'][0];
        self::assertSame(['g000000000001', '0-1'], [$first['gameId'], $first['result']]);
        self::assertSame([], $this->fixture->journal->entries, 'un résultat de partie n’est pas un acte d’administration');
    }

    public function testGameResultForAFinishedTournamentIsIgnored(): void
    {
        $id = $this->fixture->started('Open', 'round-robin', [F::ALICE, F::BOB]);
        $this->fixture->as(F::ROOT)->close($id);

        $this->fixture->service->recordGameResult('g000000000001', Result::WhiteWins);

        self::assertNull($this->fixture->as(F::ROOT)->get($id)['rounds'][0]['pairings'][0]['result']);
    }

    public function testUnknownGameIdIsIgnored(): void
    {
        $this->fixture->started('Open', 'round-robin', [F::ALICE, F::BOB]);

        $this->fixture->service->recordGameResult('g999999999999', Result::Draw);

        $this->addToAssertionCount(1);
    }
}
