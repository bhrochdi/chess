<?php

declare(strict_types=1);

namespace LeCercle\Tests\Unit\Support;

use LeCercle\Chess\Color;
use LeCercle\Game\ChessRules;
use LeCercle\Game\Game;
use LeCercle\Game\GameStatus;
use LeCercle\Shared\NotFoundException;
use LeCercle\Tests\Support\InMemoryGameRepository;
use PHPUnit\Framework\TestCase;

final class InMemoryGameRepositoryTest extends TestCase
{
    private const ALICE = 'u000000000001';
    private const BOB = 'u000000000002';

    private ChessRules $rules;
    private InMemoryGameRepository $games;
    private \DateTimeImmutable $t0;

    protected function setUp(): void
    {
        $this->rules = ChessRules::standard();
        $this->games = new InMemoryGameRepository($this->rules);
        $this->t0 = new \DateTimeImmutable('2026-11-12 10:00:00.000', new \DateTimeZone('UTC'));
    }

    private function at(int $minutes): \DateTimeImmutable
    {
        return $this->t0->modify("+$minutes minutes");
    }

    private function online(int $minutes, string $creator = self::ALICE): Game
    {
        $game = Game::online($this->games->nextId(), $creator, Color::White, null, $this->at($minutes));
        $this->games->add($game);

        return $game;
    }

    public function testNextIdCounts(): void
    {
        self::assertSame('g000000000001', $this->games->nextId());
        self::assertSame('g000000000002', $this->games->nextId());
        self::assertMatchesRegularExpression('/\Ag[0-9a-f]{12}\z/', $this->games->nextId());
    }

    public function testGetRestoresACopyByReplay(): void
    {
        $game = Game::local($this->games->nextId(), self::ALICE, null, $this->t0);
        $game->play(self::ALICE, 'e2e4', $this->rules, $this->t0);
        $this->games->add($game);

        $read = $this->games->get('g000000000001');

        self::assertNotSame($game, $read);
        self::assertSame($game->position()->toFen(), $read->position()->toFen());
        self::assertSame(1, $read->plyCount());
    }

    public function testChangesAreKeptOnlyWhenSaved(): void
    {
        $this->games->add(Game::local($this->games->nextId(), self::ALICE, null, $this->t0));

        $this->games->get('g000000000001')->play(self::ALICE, 'e2e4', $this->rules, $this->t0);
        self::assertSame(0, $this->games->get('g000000000001')->plyCount());

        $game = $this->games->getForUpdate('g000000000001');
        $game->play(self::ALICE, 'e2e4', $this->rules, $this->t0);
        $this->games->save($game);
        self::assertSame(1, $this->games->get('g000000000001')->plyCount());
    }

    public function testGetForUpdateRecordsTheLock(): void
    {
        $this->online(0);

        $this->games->get('g000000000001');
        $this->games->getForUpdate('g000000000001');

        self::assertSame(['g000000000001'], $this->games->lockedIds);
    }

    public function testUnknownGameIsNotFound(): void
    {
        foreach (['get', 'getForUpdate'] as $method) {
            try {
                $this->games->{$method}('g999999999999');
                self::fail("$method : NotFoundException attendue");
            } catch (NotFoundException $e) {
                self::assertSame('Partie introuvable.', $e->getMessage());
            }
        }
    }

    public function testAddingTwiceOrSavingAnUnknownGameIsALogicError(): void
    {
        $game = $this->online(0);
        try {
            $this->games->add($game);
            self::fail('LogicException attendue');
        } catch (\LogicException) {
        }

        $this->expectException(\LogicException::class);
        $this->games->save(Game::local('g0000000000ff', self::ALICE, null, $this->t0));
    }

    public function testLobby(): void
    {
        $open = $this->online(50);
        $playing = $this->online(40);
        $playing->join(self::BOB, $this->at(55));
        $this->games->save($playing);
        $recent = $this->online(10);
        $recent->join(self::BOB, $this->at(20));
        $recent->resign(self::ALICE, $this->at(30));
        $this->games->save($recent);
        $old = $this->online(0);
        $old->join(self::BOB, $this->at(1));
        $old->resign(self::ALICE, $this->at(2));
        $this->games->save($old);
        $this->games->add(Game::local($this->games->nextId(), self::ALICE, null, $this->at(60)));

        $ids = array_map(static fn($s): string => $s->id, $this->games->lobby($this->at(30), 30));

        self::assertSame([$playing->id(), $open->id(), $recent->id()], $ids);
        self::assertCount(2, $this->games->lobby($this->at(30), 2));
    }

    public function testEqualTimesAreOrderedById(): void
    {
        $first = $this->online(0);
        $second = $this->online(0);

        $ids = array_map(static fn($s): string => $s->id, $this->games->lobby($this->t0, 30));

        self::assertSame([$first->id(), $second->id()], $ids);
    }

    public function testUnfinishedOf(): void
    {
        $local = Game::local($this->games->nextId(), self::ALICE, null, $this->at(5));
        $this->games->add($local);
        $finished = Game::local($this->games->nextId(), self::ALICE, null, $this->at(6));
        $finished->play(self::ALICE, 'f2f3', $this->rules, $this->at(6));
        $finished->play(self::ALICE, 'e7e5', $this->rules, $this->at(6));
        $finished->play(self::ALICE, 'g2g4', $this->rules, $this->at(6));
        $finished->play(self::ALICE, 'd8h4', $this->rules, $this->at(6));
        $this->games->add($finished);
        $asBlack = $this->online(1, self::BOB);
        $asBlack->join(self::ALICE, $this->at(10));
        $this->games->save($asBlack);
        $this->online(20, self::BOB);

        $summaries = $this->games->unfinishedOf(self::ALICE, 10);

        self::assertSame(GameStatus::Finished, $finished->status());
        self::assertSame([$asBlack->id(), $local->id()], array_map(static fn($s): string => $s->id, $summaries));
        self::assertCount(1, $this->games->unfinishedOf(self::ALICE, 1));
    }
}
