<?php

declare(strict_types=1);

namespace LeCercle\Tests\Integration\Infra\Tournament;

use LeCercle\Chess\Result;
use LeCercle\Infra\Db\PdoFactory;
use LeCercle\Infra\Tournament\PdoTournamentRepository;
use LeCercle\Shared\ConflictException;
use LeCercle\Shared\NotFoundException;
use LeCercle\Shared\TimeControl;
use LeCercle\Tests\Integration\DatabaseTestCase;
use LeCercle\Tests\Integration\TestDatabase;
use LeCercle\Tournament\Pairing;
use LeCercle\Tournament\Round;
use LeCercle\Tournament\Tournament;
use LeCercle\Tournament\TournamentFormat;
use LeCercle\Tournament\TournamentSnapshot;
use LeCercle\Tournament\TournamentStatus;

final class PdoTournamentRepositoryTest extends DatabaseTestCase
{
    private function repository(): PdoTournamentRepository
    {
        return new PdoTournamentRepository(self::pdo());
    }

    private static function at(string $iso): \DateTimeImmutable
    {
        return new \DateTimeImmutable($iso, new \DateTimeZone('UTC'));
    }

    private function newTournament(string $id, string $name, TournamentFormat $format = TournamentFormat::RoundRobin, ?TimeControl $tc = null): Tournament
    {
        return Tournament::create($id, $name, 'Description de ' . $name, $format, $tc, $format === TournamentFormat::Swiss ? 3 : null, 'u000000000009', self::at('2026-11-12 10:00:00.123'));
    }

    /** @return array<string, string> */
    private static function pseudos(): array
    {
        return ['u1' => 'alice', 'u2' => 'bob', 'u3' => 'carol', 'u4' => 'dave', 'u5' => 'erin'];
    }

    private function rows(string $table, string $id): int
    {
        $stmt = self::pdo()->prepare("SELECT COUNT(*) FROM $table WHERE tournament_id = ?");
        $stmt->execute([$id]);

        return (int) $stmt->fetchColumn();
    }

    public function testNextIdFormat(): void
    {
        $repository = $this->repository();

        $a = $repository->nextId();
        $b = $repository->nextId();

        self::assertMatchesRegularExpression('/\At[0-9a-f]{12}\z/', $a);
        self::assertNotSame($a, $b);
    }

    public function testRoundTripBeforeStart(): void
    {
        $repository = $this->repository();
        $t = $this->newTournament('t00000000000a', 'Open', TournamentFormat::Elimination, new TimeControl(180, 2));
        $t->register('u2');
        $t->register('u1');
        $t->setRegistrationOpen(false);
        $repository->add($t);

        $back = $repository->get('t00000000000a');

        self::assertSame('Open', $back->name());
        self::assertSame('Description de Open', $back->description());
        self::assertSame(TournamentFormat::Elimination, $back->format());
        self::assertSame(['base' => 180, 'inc' => 2], $back->timeControl()?->toArray());
        self::assertSame(TournamentStatus::Registration, $back->status());
        self::assertFalse($back->registrationOpen());
        self::assertNull($back->plannedRounds());
        self::assertSame(0, $back->currentRound());
        self::assertSame(['u2', 'u1'], $back->players());
        self::assertSame([], $back->rounds());
        self::assertSame('u000000000009', $back->createdBy());
        self::assertSame('2026-11-12 10:00:00.123', $back->createdAt()->format('Y-m-d H:i:s.v'));
        self::assertNull($back->startedAt());
        self::assertNull($back->finishedAt());
    }

    public function testRoundTripAfterStartWithByeAndResult(): void
    {
        $repository = $this->repository();
        $t = $this->newTournament('t00000000000b', 'Suisse', TournamentFormat::Swiss, new TimeControl(600, 5));
        foreach (['u5', 'u3', 'u1', 'u4', 'u2'] as $id) {
            $t->register($id);
        }
        $repository->add($t);
        $t->start(self::pseudos(), self::at('2026-11-12 11:30:15.456'));
        $first = null;
        foreach ($t->rounds()[0]->pairings as $p) {
            if (!$p->isBye()) {
                $first = $p;
                break;
            }
        }
        self::assertNotNull($first);
        $t->setResult(1, $first->board, Result::BlackWins);
        $repository->save($t);

        $back = $repository->get('t00000000000b');

        self::assertSame(TournamentStatus::Running, $back->status());
        self::assertFalse($back->registrationOpen());
        self::assertSame(3, $back->plannedRounds());
        self::assertSame(1, $back->currentRound());
        self::assertSame(['u5', 'u3', 'u1', 'u4', 'u2'], $back->players());
        self::assertSame(['base' => 600, 'inc' => 5], $back->timeControl()?->toArray());
        self::assertSame('2026-11-12 11:30:15.456', $back->startedAt()?->format('Y-m-d H:i:s.v'));
        self::assertEquals($t->rounds(), $back->rounds());
        $pairings = $back->rounds()[0]->pairings;
        $byes = array_values(array_filter($pairings, static fn(Pairing $p): bool => $p->isBye()));
        self::assertCount(1, $byes);
        self::assertNull($byes[0]->blackId);
        self::assertSame(Result::WhiteWins, $byes[0]->result);
        $played = $back->rounds()[0]->pairing($first->board);
        self::assertSame(Result::BlackWins, $played?->result);
    }

    public function testRoundTripFinishedWithGameId(): void
    {
        $repository = $this->repository();
        $snapshot = new TournamentSnapshot(
            't00000000000c',
            'Terminé',
            '',
            TournamentFormat::RoundRobin,
            null,
            TournamentStatus::Finished,
            false,
            null,
            2,
            ['u1', 'u2', 'u3'],
            [
                new Round(1, [new Pairing(1, 'u1', 'u2', Result::Draw, 'g00000000001'), Pairing::bye(2, 'u3')]),
                new Round(2, [new Pairing(1, 'u3', 'u1', null, null), new Pairing(2, null, 'u2', Result::WhiteWins)]),
            ],
            'u000000000009',
            self::at('2026-11-10 08:00:00.001'),
            self::at('2026-11-10 09:00:00.002'),
            self::at('2026-11-11 21:59:59.999'),
        );
        $repository->add(Tournament::restore($snapshot));

        $back = $repository->get('t00000000000c');

        self::assertSame(TournamentStatus::Finished, $back->status());
        self::assertNull($back->timeControl());
        self::assertSame('', $back->description());
        self::assertSame('2026-11-11 21:59:59.999', $back->finishedAt()?->format('Y-m-d H:i:s.v'));
        self::assertEquals($snapshot->rounds, $back->rounds());
        self::assertSame('g00000000001', $back->rounds()[0]->pairings[0]->gameId);
    }

    public function testSaveReplacesPlayersAndPairings(): void
    {
        $repository = $this->repository();
        $t = $this->newTournament('t00000000000d', 'Rondes');
        foreach (['u1', 'u2', 'u3'] as $id) {
            $t->register($id);
        }
        $repository->add($t);
        $t->unregister('u2');
        $repository->save($t);
        self::assertSame(['u1', 'u3'], $repository->get('t00000000000d')->players());

        $t->start(self::pseudos(), self::at('2026-11-12 12:00:00.000'));
        $repository->save($t);
        $repository->save($t);
        self::assertSame(1, \count($t->rounds()));
        self::assertSame(\count($t->rounds()[0]->pairings), $this->rows('tournament_pairings', 't00000000000d'));
        self::assertSame(2, $this->rows('tournament_players', 't00000000000d'));
    }

    public function testSaveOnlyTouchesItsOwnTournament(): void
    {
        $repository = $this->repository();
        $a = $this->newTournament('t0000000000a1', 'Alpha');
        $b = $this->newTournament('t0000000000b1', 'Bravo');
        $a->register('u1');
        $b->register('u2');
        $b->register('u3');
        $repository->add($a);
        $repository->add($b);

        $a->register('u4');
        $repository->save($a);

        self::assertSame(['u2', 'u3'], $repository->get('t0000000000b1')->players());
        self::assertSame(['u1', 'u4'], $repository->get('t0000000000a1')->players());
        self::assertSame('Bravo', $repository->get('t0000000000b1')->name());
    }

    public function testUnknownIdIsNotFound(): void
    {
        $this->expectException(NotFoundException::class);
        $this->expectExceptionMessage('Tournoi introuvable.');

        $this->repository()->get('t000000000000');
    }

    public function testGetForUpdateUnknownIdIsNotFound(): void
    {
        $this->expectException(NotFoundException::class);
        $this->expectExceptionMessage('Tournoi introuvable.');

        $this->repository()->getForUpdate('t000000000000');
    }

    public function testGetForUpdateReadsLikeGetAndLocksTheRow(): void
    {
        $repository = $this->repository();
        $t = $this->newTournament('t00000000000e', 'Verrou');
        $t->register('u1');
        $t->register('u2');
        $repository->add($t);

        $pdo = self::pdo();
        $pdo->beginTransaction();
        try {
            $locked = $repository->getForUpdate('t00000000000e');
            self::assertEquals($repository->get('t00000000000e'), $locked);

            $other = PdoFactory::create(TestDatabase::config());
            $other->beginTransaction();
            try {
                $this->expectException(\PDOException::class);
                $other->query("SELECT id FROM tournaments WHERE id = 't00000000000e' FOR UPDATE NOWAIT");
            } finally {
                $other->rollBack();
            }
        } finally {
            $pdo->rollBack();
        }
    }

    public function testGetDoesNotLockTheRow(): void
    {
        $repository = $this->repository();
        $repository->add($this->newTournament('t00000000000f', 'Libre'));

        $pdo = self::pdo();
        $pdo->beginTransaction();
        try {
            $repository->get('t00000000000f');
            $other = PdoFactory::create(TestDatabase::config());
            $other->beginTransaction();
            $rows = $other->query("SELECT id FROM tournaments WHERE id = 't00000000000f' FOR UPDATE NOWAIT");
            self::assertNotFalse($rows);
            self::assertCount(1, $rows->fetchAll());
            $other->rollBack();
        } finally {
            $pdo->rollBack();
        }
    }

    public function testSummariesCountPlayers(): void
    {
        $repository = $this->repository();
        $a = $this->newTournament('t0000000000a2', 'Trois', TournamentFormat::Swiss, new TimeControl(300, 0));
        foreach (['u1', 'u2', 'u3'] as $id) {
            $a->register($id);
        }
        $b = $this->newTournament('t0000000000b2', 'Zéro');
        $repository->add($a);
        $repository->add($b);

        $byId = [];
        foreach ($repository->summaries() as $s) {
            $byId[$s->id] = $s;
        }

        self::assertCount(2, $byId);
        self::assertSame(3, $byId['t0000000000a2']->playerCount);
        self::assertSame(0, $byId['t0000000000b2']->playerCount);
        self::assertSame('Trois', $byId['t0000000000a2']->name);
        self::assertSame(TournamentFormat::Swiss, $byId['t0000000000a2']->format);
        self::assertSame(['base' => 300, 'inc' => 0], $byId['t0000000000a2']->timeControl?->toArray());
        self::assertNull($byId['t0000000000b2']->timeControl);
        self::assertSame(TournamentStatus::Registration, $byId['t0000000000b2']->status);
        self::assertNull($byId['t0000000000b2']->startedAt);
        self::assertSame('2026-11-12 10:00:00.123', $byId['t0000000000b2']->createdAt->format('Y-m-d H:i:s.v'));
    }

    public function testSummariesCarryStartedAt(): void
    {
        $repository = $this->repository();
        $t = $this->newTournament('t0000000000a3', 'Lancé');
        $t->register('u1');
        $t->register('u2');
        $t->start(self::pseudos(), self::at('2026-11-12 13:00:00.500'));
        $repository->add($t);

        $summaries = $repository->summaries();

        self::assertCount(1, $summaries);
        self::assertSame('2026-11-12 13:00:00.500', $summaries[0]->startedAt?->format('Y-m-d H:i:s.v'));
        self::assertSame(2, $summaries[0]->playerCount);
        self::assertSame(TournamentStatus::Running, $summaries[0]->status);
    }

    public function testSummariesEmpty(): void
    {
        self::assertSame([], $this->repository()->summaries());
    }

    public function testNameTakenIgnoresCaseAndExcludesId(): void
    {
        $repository = $this->repository();
        $repository->add($this->newTournament('t0000000000a4', 'Grand Prix'));

        self::assertTrue($repository->nameTaken('grand prix'));
        self::assertTrue($repository->nameTaken('GRAND PRIX'));
        self::assertFalse($repository->nameTaken('Grand Prix 2'));
        self::assertFalse($repository->nameTaken('Grand Prix', 't0000000000a4'));
        self::assertTrue($repository->nameTaken('Grand Prix', 't0000000000zz'));
    }

    public function testRunningExistsExcludesId(): void
    {
        $repository = $this->repository();
        $repository->add($this->newTournament('t0000000000a5', 'Attente'));
        self::assertFalse($repository->runningExists());

        $running = $this->newTournament('t0000000000b5', 'Courant');
        $running->register('u1');
        $running->register('u2');
        $running->start(self::pseudos(), self::at('2026-11-12 14:00:00.000'));
        $repository->add($running);

        self::assertTrue($repository->runningExists());
        self::assertTrue($repository->runningExists('t0000000000a5'));
        self::assertFalse($repository->runningExists('t0000000000b5'));
    }

    public function testFinishedTournamentIsNotRunning(): void
    {
        $repository = $this->repository();
        $t = $this->newTournament('t0000000000a6', 'Fini');
        $t->register('u1');
        $t->register('u2');
        $t->start(self::pseudos(), self::at('2026-11-12 14:00:00.000'));
        $t->close(self::at('2026-11-12 15:00:00.000'));
        $repository->add($t);

        self::assertFalse($repository->runningExists());
        self::assertSame(TournamentStatus::Finished, $repository->get('t0000000000a6')->status());
    }

    public function testDeleteCascades(): void
    {
        $repository = $this->repository();
        $t = $this->newTournament('t0000000000a7', 'Supprimé');
        $t->register('u1');
        $t->register('u2');
        $t->start(self::pseudos(), self::at('2026-11-12 14:00:00.000'));
        $repository->add($t);
        $other = $this->newTournament('t0000000000b7', 'Gardé');
        $other->register('u1');
        $repository->add($other);
        self::assertGreaterThan(0, $this->rows('tournament_pairings', 't0000000000a7'));

        $repository->delete('t0000000000a7');

        $stmt = self::pdo()->prepare('SELECT COUNT(*) FROM tournaments WHERE id = ?');
        $stmt->execute(['t0000000000a7']);
        self::assertSame(0, (int) $stmt->fetchColumn());
        self::assertSame(0, $this->rows('tournament_players', 't0000000000a7'));
        self::assertSame(0, $this->rows('tournament_pairings', 't0000000000a7'));
        self::assertSame(1, $this->rows('tournament_players', 't0000000000b7'));
        self::assertSame('Gardé', $repository->get('t0000000000b7')->name());
    }

    public function testAddDuplicateNameBecomesConflict(): void
    {
        $repository = $this->repository();
        $repository->add($this->newTournament('t0000000000a8', 'Open'));

        try {
            $repository->add($this->newTournament('t0000000000b8', 'open'));
            self::fail('Un nom en double doit être refusé.');
        } catch (ConflictException $e) {
            self::assertSame('Ce nom de tournoi est déjà pris.', $e->getMessage());
            self::assertInstanceOf(\PDOException::class, $e->getPrevious());
        }
        self::assertSame(0, $this->rows('tournament_players', 't0000000000b8'));
    }

    public function testSaveDuplicateNameBecomesConflict(): void
    {
        $repository = $this->repository();
        $repository->add($this->newTournament('t0000000000a9', 'Premier'));
        $second = $this->newTournament('t0000000000b9', 'Second');
        $repository->add($second);
        $clash = Tournament::restore(new TournamentSnapshot(
            't0000000000b9',
            'PREMIER',
            '',
            TournamentFormat::RoundRobin,
            null,
            TournamentStatus::Registration,
            true,
            null,
            0,
            [],
            [],
            'u000000000009',
            self::at('2026-11-12 10:00:00.123'),
            null,
            null,
        ));

        $this->expectException(ConflictException::class);
        $this->expectExceptionMessage('Ce nom de tournoi est déjà pris.');

        $repository->save($clash);
    }

    public function testDuplicateIdIsNotReportedAsADuplicateName(): void
    {
        $repository = $this->repository();
        $repository->add($this->newTournament('t0000000000d8', 'Premier'));

        try {
            $repository->add($this->newTournament('t0000000000d8', 'Second'));
            self::fail('Un identifiant en double doit être refusé.');
        } catch (ConflictException) {
            self::fail('Seule la clé du nom devient « nom déjà pris ».');
        } catch (\PDOException $e) {
            self::assertSame(1062, $e->errorInfo[1] ?? null);
        }
    }

    public function testOtherDatabaseErrorsAreNotMasked(): void
    {
        $repository = $this->repository();
        $tooLong = Tournament::restore(new TournamentSnapshot(
            't0000000000c8',
            'Nom',
            '',
            TournamentFormat::RoundRobin,
            null,
            TournamentStatus::Registration,
            true,
            null,
            0,
            [],
            [],
            str_repeat('x', 40),
            self::at('2026-11-12 10:00:00.123'),
            null,
            null,
        ));

        $this->expectException(\PDOException::class);

        $repository->add($tooLong);
    }

    public function testLinkedGameRoundTripsAndFindsItsTournamentUnderLock(): void
    {
        $repository = $this->repository();
        $t = $this->newTournament('t0000000000e1', 'Lien');
        $t->register('u1');
        $t->register('u2');
        $repository->add($t);
        $t->start(self::pseudos(), self::at('2026-11-12 11:00:00.000'));
        $t->linkGame(1, 'g0000000000e1');
        $repository->save($t);

        self::assertSame('g0000000000e1', $repository->get('t0000000000e1')->rounds()[0]->pairings[0]->gameId);
        self::assertNull($repository->findByGameId('g0000000000zz'));

        $pdo = self::pdo();
        $pdo->beginTransaction();
        try {
            self::assertSame('t0000000000e1', $repository->findByGameId('g0000000000e1')?->id());
            $other = PdoFactory::create(TestDatabase::config());
            $other->beginTransaction();
            try {
                $this->expectException(\PDOException::class);
                $other->query("SELECT id FROM tournaments WHERE id = 't0000000000e1' FOR UPDATE NOWAIT");
            } finally {
                $other->rollBack();
            }
        } finally {
            $pdo->rollBack();
        }
    }
}
