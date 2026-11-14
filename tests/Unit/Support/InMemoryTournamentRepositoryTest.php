<?php

declare(strict_types=1);

namespace LeCercle\Tests\Unit\Support;

use LeCercle\Chess\Result;
use LeCercle\Shared\NotFoundException;
use LeCercle\Shared\TimeControl;
use LeCercle\Tests\Support\InMemoryTournamentRepository;
use LeCercle\Tournament\Tournament;
use LeCercle\Tournament\TournamentFormat;
use LeCercle\Tournament\TournamentStatus;
use PHPUnit\Framework\TestCase;

final class InMemoryTournamentRepositoryTest extends TestCase
{
    private \DateTimeImmutable $t0;
    private InMemoryTournamentRepository $repo;

    protected function setUp(): void
    {
        $this->t0 = new \DateTimeImmutable('2026-11-12 10:00:00', new \DateTimeZone('UTC'));
        $this->repo = new InMemoryTournamentRepository();
    }

    private function tournament(string $name = 'Open', TournamentFormat $format = TournamentFormat::RoundRobin): Tournament
    {
        return Tournament::create($this->repo->nextId(), $name, 'desc', $format, new TimeControl(600, 5), 4, 'R', $this->t0);
    }

    public function testIdsAreSequential(): void
    {
        self::assertSame('t000000000001', $this->repo->nextId());
        self::assertSame('t000000000002', $this->repo->nextId());
    }

    public function testRoundTripKeepsTheWholeState(): void
    {
        $t = $this->tournament('Suisse', TournamentFormat::Swiss);
        foreach (['A', 'B', 'C'] as $id) {
            $t->register($id);
        }
        $t->start([], $this->t0->modify('+1 hour'));
        $t->setResult(1, 1, Result::Draw);
        $this->repo->add($t);

        $copy = $this->repo->get($t->id());

        self::assertNotSame($t, $copy);
        self::assertEquals($t, $copy);
        self::assertEquals($t, $this->repo->getForUpdate($t->id()));
    }

    public function testChangesNeedSave(): void
    {
        $t = $this->tournament();
        $this->repo->add($t);

        $copy = $this->repo->get($t->id());
        $copy->register('A');
        self::assertSame([], $this->repo->get($t->id())->players(), 'non enregistré');

        $this->repo->save($copy);
        self::assertSame(['A'], $this->repo->get($t->id())->players());
    }

    public function testUnknownAndDeleted(): void
    {
        $t = $this->tournament();
        $this->repo->add($t);
        $this->repo->delete($t->id());

        $this->expectException(NotFoundException::class);
        $this->expectExceptionMessage('Tournoi introuvable.');

        $this->repo->getForUpdate($t->id());
    }

    public function testNameTakenIgnoresCaseAndTheExcludedId(): void
    {
        $t = $this->tournament('Öpen');
        $this->repo->add($t);

        self::assertTrue($this->repo->nameTaken('öPEN'));
        self::assertFalse($this->repo->nameTaken('öPEN', $t->id()));
        self::assertFalse($this->repo->nameTaken('Blitz'));
    }

    public function testRunningExistsAndSummaries(): void
    {
        $open = $this->tournament('Open');
        $running = $this->tournament('Blitz', TournamentFormat::Elimination);
        $running->register('A');
        $running->register('B');
        $running->start([], $this->t0->modify('+1 hour'));
        $this->repo->add($open);
        self::assertFalse($this->repo->runningExists());
        $this->repo->add($running);

        self::assertTrue($this->repo->runningExists());
        self::assertTrue($this->repo->runningExists($open->id()));
        self::assertFalse($this->repo->runningExists($running->id()));

        $summaries = $this->repo->summaries();
        self::assertCount(2, $summaries);
        $s = $summaries[1];
        self::assertSame([$running->id(), 'Blitz', TournamentFormat::Elimination, TournamentStatus::Running, 2], [$s->id, $s->name, $s->format, $s->status, $s->playerCount]);
        self::assertEquals(new TimeControl(600, 5), $s->timeControl);
        self::assertEquals($this->t0->modify('+1 hour'), $s->startedAt);
        self::assertEquals($this->t0, $s->createdAt);
    }
}
