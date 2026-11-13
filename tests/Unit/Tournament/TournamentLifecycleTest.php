<?php

declare(strict_types=1);

namespace LeCercle\Tests\Unit\Tournament;

use LeCercle\Chess\Result;
use LeCercle\Shared\ConflictException;
use LeCercle\Shared\NotFoundException;
use LeCercle\Shared\TimeControl;
use LeCercle\Shared\ValidationException;
use LeCercle\Tournament\Pairing;
use LeCercle\Tournament\Round;
use LeCercle\Tournament\Tournament;
use LeCercle\Tournament\TournamentFormat;
use LeCercle\Tournament\TournamentSnapshot;
use LeCercle\Tournament\TournamentStatus;
use PHPUnit\Framework\TestCase;

final class TournamentLifecycleTest extends TestCase
{
    private const P = ['A' => 'alice', 'B' => 'bob', 'C' => 'carol', 'D' => 'dave'];

    private \DateTimeImmutable $t0;
    private \DateTimeImmutable $t1;

    protected function setUp(): void
    {
        $this->t0 = new \DateTimeImmutable('2026-11-12 10:00:00', new \DateTimeZone('UTC'));
        $this->t1 = new \DateTimeImmutable('2026-11-13 18:00:00', new \DateTimeZone('UTC'));
    }

    /** @param list<string> $players */
    private function tournament(TournamentFormat $format, array $players = [], ?int $rounds = null): Tournament
    {
        $t = Tournament::create('t1', 'Open', '', $format, new TimeControl(600, 0), $rounds, 'root', $this->t0);
        foreach ($players as $id) {
            $t->register($id);
        }

        return $t;
    }

    /** @param list<string> $players */
    private function started(TournamentFormat $format, array $players = ['A', 'B', 'C', 'D'], ?int $rounds = null): Tournament
    {
        $t = $this->tournament($format, $players, $rounds);
        $t->start(self::P, $this->t0);

        return $t;
    }

    private function completeCurrentRound(Tournament $t): void
    {
        foreach ($t->rounds()[$t->currentRound() - 1]->pairings as $p) {
            if (!$p->isBye()) {
                $t->setResult($t->currentRound(), $p->board, Result::WhiteWins);
            }
        }
    }

    private function pairing(Tournament $t, int $roundIndex, int $board): Pairing
    {
        $p = $t->rounds()[$roundIndex]->pairing($board);
        self::assertNotNull($p);

        return $p;
    }

    /** @param class-string<\Throwable> $class */
    private function assertThrows(string $class, string $message, callable $fn): void
    {
        try {
            $fn();
        } catch (\Throwable $e) {
            self::assertInstanceOf($class, $e);
            self::assertSame($message, $e->getMessage());

            return;
        }
        self::fail('Exception attendue : ' . $message);
    }

    public function testCreateOpensRegistration(): void
    {
        $t = Tournament::create('t1', ' Open ', ' Blitz ', TournamentFormat::RoundRobin, new TimeControl(600, 0), 7, 'root', $this->t0);
        self::assertSame('t1', $t->id());
        self::assertSame('Open', $t->name());
        self::assertSame('Blitz', $t->description());
        self::assertSame(TournamentFormat::RoundRobin, $t->format());
        self::assertEquals(new TimeControl(600, 0), $t->timeControl());
        self::assertSame(TournamentStatus::Registration, $t->status());
        self::assertTrue($t->registrationOpen());
        self::assertSame(0, $t->currentRound());
        self::assertNull($t->plannedRounds(), 'le nombre de rondes ne vaut que pour le suisse');
        self::assertSame([], $t->players());
        self::assertSame([], $t->rounds());
        self::assertSame('root', $t->createdBy());
        self::assertSame($this->t0, $t->createdAt());
        self::assertNull($t->startedAt());
        self::assertNull($t->finishedAt());
        self::assertFalse($t->canAdvance());

        self::assertSame(5, $this->tournament(TournamentFormat::Swiss)->plannedRounds());
        self::assertSame(3, $this->tournament(TournamentFormat::Swiss, [], 3)->plannedRounds());

        $this->assertThrows(ValidationException::class, 'Nom trop court (2 caractères minimum).', fn() => Tournament::create('t2', 'O', '', TournamentFormat::RoundRobin, null, null, 'root', $this->t0));
        $this->assertThrows(ValidationException::class, 'Description trop longue (500 caractères maximum).', fn() => Tournament::create('t2', 'Open', str_repeat('é', 501), TournamentFormat::RoundRobin, null, null, 'root', $this->t0));
        $this->assertThrows(ValidationException::class, 'Nombre de rondes : entre 2 et 15.', fn() => Tournament::create('t2', 'Open', '', TournamentFormat::Swiss, null, 1, 'root', $this->t0));
    }

    public function testRegistration(): void
    {
        $t = $this->tournament(TournamentFormat::RoundRobin, ['A', 'B', 'A']);
        self::assertSame(['A', 'B'], $t->players());

        $t->setRegistrationOpen(false);
        self::assertFalse($t->registrationOpen());
        $this->assertThrows(ConflictException::class, 'Les inscriptions sont fermées.', fn() => $t->register('C'));

        $t->unregister('B');
        $t->unregister('Z');
        self::assertSame(['A'], $t->players());

        $t->setRegistrationOpen(true);
        $t->register('C');
        $t->register('D');
        $t->unregister('C');
        self::assertSame(['A', 'D'], $t->players(), 'ordre d’inscription conservé, liste réindexée');
    }

    public function testStartNeedsTwoPlayers(): void
    {
        $t = $this->tournament(TournamentFormat::RoundRobin, ['A']);
        $this->assertThrows(ConflictException::class, 'Au moins 2 joueurs inscrits sont nécessaires.', fn() => $t->start(self::P, $this->t0));
        self::assertSame(TournamentStatus::Registration, $t->status());
    }

    public function testStartRoundRobin(): void
    {
        $t = $this->tournament(TournamentFormat::RoundRobin, ['A', 'B', 'C', 'D']);
        $t->start(self::P, $this->t0);
        self::assertSame(TournamentStatus::Running, $t->status());
        self::assertSame(1, $t->currentRound());
        self::assertCount(3, $t->rounds());
        self::assertSame([1, 2, 3], array_map(static fn(Round $r): int => $r->number, $t->rounds()));
        self::assertFalse($t->registrationOpen());
        self::assertSame($this->t0, $t->startedAt());

        $this->assertThrows(ConflictException::class, 'Le tournoi a déjà commencé.', fn() => $t->start(self::P, $this->t1));
        $this->assertThrows(ConflictException::class, 'Les inscriptions sont fermées.', fn() => $t->register('E'));
        $this->assertThrows(ConflictException::class, 'Les inscriptions sont closes : le tournoi a commencé.', fn() => $t->unregister('A'));
        $this->assertThrows(ConflictException::class, 'Les inscriptions sont closes : le tournoi a commencé.', fn() => $t->setRegistrationOpen(true));
        self::assertSame(['A', 'B', 'C', 'D'], $t->players());
    }

    public function testStartEliminationAndSwissBuildOnlyTheFirstRound(): void
    {
        $e = $this->started(TournamentFormat::Elimination);
        self::assertCount(1, $e->rounds());
        self::assertEquals(new Pairing(1, 'A', 'D'), $e->rounds()[0]->pairing(1));

        // Suisse : ordre du classement initial (tous à 0, départage par pseudo).
        $s = $this->tournament(TournamentFormat::Swiss, ['D', 'C', 'B', 'A'], 2);
        $s->start(self::P, $this->t0);
        self::assertCount(1, $s->rounds());
        self::assertSame(1, $s->rounds()[0]->number);
        self::assertEquals([new Pairing(1, 'A', 'B'), new Pairing(2, 'C', 'D')], $s->rounds()[0]->pairings);
    }

    public function testSetResultRules(): void
    {
        $t = $this->started(TournamentFormat::RoundRobin);
        $updated = $t->setResult(1, 1, Result::WhiteWins);
        self::assertSame(Result::WhiteWins, $updated->result);
        self::assertSame(1, $updated->board);
        self::assertSame(Result::WhiteWins, $this->pairing($t, 0, 1)->result);
        self::assertNull($this->pairing($t, 0, 2)->result);

        $this->assertThrows(ConflictException::class, 'Cette ronde n’a pas commencé.', fn() => $t->setResult(2, 1, Result::Draw));
        $this->assertThrows(ConflictException::class, 'Cette ronde n’a pas commencé.', fn() => $t->setResult(0, 1, Result::Draw));
        $this->assertThrows(NotFoundException::class, 'Match introuvable.', fn() => $t->setResult(1, 9, Result::Draw));

        self::assertNull($t->setResult(1, 1, null)->result);
        self::assertNull($this->pairing($t, 0, 1)->result);

        $e = $this->started(TournamentFormat::Elimination, ['A', 'B', 'C']);
        self::assertTrue($this->pairing($e, 0, 1)->isBye());
        $this->assertThrows(ConflictException::class, 'Un exempt n’a pas de résultat à saisir.', fn() => $e->setResult(1, 1, Result::BlackWins));
        self::assertSame(Result::WhiteWins, $this->pairing($e, 0, 1)->result);

        $r = $this->tournament(TournamentFormat::RoundRobin, ['A', 'B']);
        $this->assertThrows(ConflictException::class, 'Le tournoi n’est pas en cours.', fn() => $r->setResult(1, 1, Result::Draw));
    }

    public function testPastRoundIsLocked(): void
    {
        $t = $this->started(TournamentFormat::RoundRobin);
        $t->setResult(1, 1, Result::WhiteWins);
        $t->nextRound(true, self::P);
        $this->assertThrows(ConflictException::class, 'Seuls les résultats de la ronde en cours peuvent être modifiés.', fn() => $t->setResult(1, 1, Result::BlackWins));
        self::assertSame(Result::WhiteWins, $this->pairing($t, 0, 1)->result);
        self::assertSame(Result::Draw, $t->setResult(2, 1, Result::Draw)->result);

        $e = $this->started(TournamentFormat::Elimination);
        $this->completeCurrentRound($e);
        $e->nextRound(false, self::P);
        $this->assertThrows(ConflictException::class, 'Seuls les résultats de la ronde en cours peuvent être modifiés.', fn() => $e->setResult(1, 2, Result::BlackWins));
    }

    public function testRoundRobinNextRound(): void
    {
        $t = $this->started(TournamentFormat::RoundRobin);
        self::assertTrue($t->canAdvance());
        $t->setResult(1, 1, Result::WhiteWins);
        $this->assertThrows(ConflictException::class, 'Des résultats manquent à la ronde 1.', fn() => $t->nextRound(false, self::P));
        self::assertSame(1, $t->currentRound());

        $t->nextRound(true, self::P);
        self::assertSame(2, $t->currentRound());
        self::assertCount(3, $t->rounds(), 'les rondes toutes rondes sont générées au lancement');

        $this->completeCurrentRound($t);
        $t->nextRound(false, self::P);
        self::assertSame(3, $t->currentRound());
        self::assertFalse($t->canAdvance());

        $this->completeCurrentRound($t);
        $this->assertThrows(ConflictException::class, 'Dernière ronde atteinte : clôture le tournoi.', fn() => $t->nextRound(true, self::P));
        self::assertSame(3, $t->currentRound());
    }

    public function testEliminationFlow(): void
    {
        $t = $this->started(TournamentFormat::Elimination);
        self::assertTrue($t->canAdvance());
        $t->setResult(1, 1, Result::WhiteWins);  // A bat D
        $t->setResult(1, 2, Result::BlackWins);  // C bat B
        $t->nextRound(false, self::P);
        self::assertSame(2, $t->currentRound());
        self::assertCount(2, $t->rounds());
        self::assertEquals(new Round(2, [new Pairing(1, 'A', 'C')]), $t->rounds()[1]);
        self::assertFalse($t->canAdvance());

        $t->setResult(2, 1, Result::WhiteWins);
        $this->assertThrows(ConflictException::class, 'Il ne reste qu’un joueur : clôture le tournoi.', fn() => $t->nextRound(false, self::P));
        self::assertCount(2, $t->rounds());

        $d = $this->started(TournamentFormat::Elimination);
        $d->setResult(1, 1, Result::Draw);
        $d->setResult(1, 2, Result::WhiteWins);
        $this->assertThrows(ConflictException::class, 'Élimination : un match est nul. Saisis un résultat décisif (départage) avant de continuer.', fn() => $d->nextRound(false, self::P));
        self::assertSame(1, $d->currentRound());
    }

    public function testEliminationNeedsCompleteRound(): void
    {
        $t = $this->started(TournamentFormat::Elimination);
        $t->setResult(1, 1, Result::WhiteWins);
        $this->assertThrows(ConflictException::class, 'Complète tous les résultats de la ronde avant de générer la suivante.', fn() => $t->nextRound(true, self::P));
        self::assertCount(1, $t->rounds());

        $s = $this->started(TournamentFormat::Swiss, rounds: 2);
        $this->assertThrows(ConflictException::class, 'Complète tous les résultats de la ronde avant de générer la suivante.', fn() => $s->nextRound(true, self::P));
    }

    public function testSwissStopsAtPlannedRounds(): void
    {
        $t = $this->started(TournamentFormat::Swiss, rounds: 2);
        self::assertTrue($t->canAdvance());
        $this->completeCurrentRound($t);   // A bat B, C bat D
        $t->nextRound(false, self::P);
        self::assertSame(2, $t->currentRound());
        self::assertCount(2, $t->rounds());
        // Classement A, C (1 pt) puis B, D : sans revanche, A-C et B-D.
        self::assertEquals(new Round(2, [new Pairing(1, 'A', 'C'), new Pairing(2, 'B', 'D')]), $t->rounds()[1]);
        self::assertFalse($t->canAdvance());

        $this->completeCurrentRound($t);
        $this->assertThrows(ConflictException::class, 'Nombre de rondes du suisse atteint : clôture le tournoi.', fn() => $t->nextRound(false, self::P));
        self::assertCount(2, $t->rounds());
    }

    public function testClose(): void
    {
        $r = $this->tournament(TournamentFormat::RoundRobin, ['A', 'B']);
        $this->assertThrows(ConflictException::class, 'Le tournoi n’est pas en cours.', fn() => $r->close($this->t1));

        $t = $this->started(TournamentFormat::RoundRobin);
        $t->close($this->t1);
        self::assertSame(TournamentStatus::Finished, $t->status());
        self::assertSame($this->t1, $t->finishedAt());
        self::assertFalse($t->canAdvance());

        $this->assertThrows(ConflictException::class, 'Le tournoi n’est pas en cours.', fn() => $t->close($this->t1));
        $this->assertThrows(ConflictException::class, 'Le tournoi n’est pas en cours.', fn() => $t->nextRound(true, self::P));
        $this->assertThrows(ConflictException::class, 'Tournoi terminé.', fn() => $t->setResult(1, 1, Result::WhiteWins));
        $this->assertThrows(ConflictException::class, 'Le tournoi a déjà commencé.', fn() => $t->start(self::P, $this->t1));
        $this->assertThrows(ConflictException::class, 'Les inscriptions sont fermées.', fn() => $t->register('E'));
        self::assertNull($this->pairing($t, 0, 1)->result);
    }

    public function testStandingsFollowResults(): void
    {
        $t = $this->started(TournamentFormat::RoundRobin);
        $t->setResult(1, 1, Result::WhiteWins);  // A bat D
        $rows = $t->standings(self::P);
        self::assertCount(4, $rows);
        self::assertSame('A', $rows[0]->playerId);
        self::assertSame(1.0, $rows[0]->points());
        self::assertSame('D', $rows[3]->playerId);
    }

    public function testRestoreKeepsState(): void
    {
        $rounds = [new Round(1, [new Pairing(1, 'A', 'B', Result::WhiteWins)]), new Round(2, [new Pairing(1, 'A', 'C')])];
        $t = Tournament::restore(new TournamentSnapshot(
            id: 't9',
            name: 'Suisse',
            description: 'd',
            format: TournamentFormat::Swiss,
            timeControl: null,
            status: TournamentStatus::Running,
            registrationOpen: false,
            plannedRounds: 3,
            currentRound: 2,
            players: ['A', 'B', 'C'],
            rounds: $rounds,
            createdBy: 'root',
            createdAt: $this->t0,
            startedAt: $this->t1,
            finishedAt: null,
        ));
        self::assertSame('t9', $t->id());
        self::assertSame('Suisse', $t->name());
        self::assertSame('d', $t->description());
        self::assertSame(TournamentFormat::Swiss, $t->format());
        self::assertNull($t->timeControl());
        self::assertSame(TournamentStatus::Running, $t->status());
        self::assertFalse($t->registrationOpen());
        self::assertSame(3, $t->plannedRounds());
        self::assertSame(2, $t->currentRound());
        self::assertSame(['A', 'B', 'C'], $t->players());
        self::assertSame($rounds, $t->rounds());
        self::assertSame('root', $t->createdBy());
        self::assertSame($this->t0, $t->createdAt());
        self::assertSame($this->t1, $t->startedAt());
        self::assertNull($t->finishedAt());
        self::assertTrue($t->canAdvance());
        $this->assertThrows(ConflictException::class, 'Seuls les résultats de la ronde en cours peuvent être modifiés.', fn() => $t->setResult(1, 1, Result::Draw));
    }
}
