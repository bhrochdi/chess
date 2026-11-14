<?php

declare(strict_types=1);

namespace LeCercle\Tests\Unit\Tournament;

use LeCercle\Shared\Access;
use LeCercle\Shared\AdminJournal;
use LeCercle\Shared\Member;
use LeCercle\Shared\Members;
use LeCercle\Shared\Role;
use LeCercle\Shared\TransactionRunner;
use LeCercle\Tests\Support\FakeSession;
use LeCercle\Tests\Support\FixedClock;
use LeCercle\Tests\Support\InMemoryMembers;
use LeCercle\Tests\Support\InMemoryTournamentRepository;
use LeCercle\Tests\Support\RecordingAdminJournal;
use LeCercle\Tests\Support\RecordingPairingGames;
use LeCercle\Tournament\TournamentService;
use LeCercle\Tournament\TournamentViews;

/**
 * Montage commun des tests du service : membres alice, bob, carol, dave, zoe
 * (identifiant le plus petit, pseudo le dernier dans l'ordre alphabétique),
 * eve suspendue, un invité et l'admin root.
 */
final class TournamentServiceFixture
{
    public const ALICE = 'u000000000001';
    public const BOB = 'u000000000002';
    public const CAROL = 'u000000000003';
    public const DAVE = 'u000000000004';
    public const EVE = 'u000000000005';
    public const GUEST = 'u000000000006';
    public const ZOE = 'u000000000000';
    public const ROOT = 'u000000000009';

    public readonly InMemoryMembers $members;
    public readonly FakeSession $session;
    public readonly InMemoryTournamentRepository $repository;
    public readonly RecordingAdminJournal $journal;
    public readonly FixedClock $clock;
    public readonly TournamentService $service;
    public readonly RecordingPairingGames $games;

    /** Nombre d'appels à Members::findMany depuis le dernier reset. */
    public int $findManyCalls = 0;

    /** @var list<string> comptes supprimés depuis : absents des lectures de membres */
    public array $deleted = [];

    /** Vrai pendant l'exécution d'un travail transactionnel. */
    public bool $inTransaction = false;

    /** @var list<bool> pour chaque entrée du journal : écrite dans la transaction ? */
    public array $journalInTransaction = [];

    public function __construct()
    {
        $this->members = new InMemoryMembers(
            new Member(self::ALICE, 'alice', Role::User, false, false),
            new Member(self::BOB, 'bob', Role::User, false, false),
            new Member(self::CAROL, 'carol', Role::User, false, false),
            new Member(self::DAVE, 'dave', Role::User, false, false),
            new Member(self::ZOE, 'zoe', Role::User, false, false),
            new Member(self::EVE, 'eve', Role::User, true, false),
            new Member(self::GUEST, 'Invite_ab12', Role::User, false, true),
            new Member(self::ROOT, 'root', Role::Admin, false, false),
        );
        $this->session = new FakeSession();
        $this->repository = new InMemoryTournamentRepository();
        $this->journal = new RecordingAdminJournal();
        $this->clock = new FixedClock(new \DateTimeImmutable('2026-11-12 10:00:00', new \DateTimeZone('UTC')));

        $fixture = $this;
        $counting = new class ($this->members, $fixture) implements Members {
            public function __construct(private readonly Members $inner, private readonly TournamentServiceFixture $fixture) {}

            public function find(string $id): ?Member
            {
                return \in_array($id, $this->fixture->deleted, true) ? null : $this->inner->find($id);
            }

            public function findByPseudo(string $pseudo): ?Member
            {
                return $this->inner->findByPseudo($pseudo);
            }

            public function findMany(array $ids): array
            {
                ++$this->fixture->findManyCalls;

                return array_diff_key($this->inner->findMany($ids), array_flip($this->fixture->deleted));
            }
        };
        $transactions = new class ($fixture) implements TransactionRunner {
            public function __construct(private readonly TournamentServiceFixture $fixture) {}

            public function run(callable $work): mixed
            {
                $this->fixture->inTransaction = true;
                try {
                    return $work();
                } finally {
                    $this->fixture->inTransaction = false;
                }
            }
        };
        $journal = new class ($this->journal, $fixture) implements AdminJournal {
            public function __construct(private readonly RecordingAdminJournal $inner, private readonly TournamentServiceFixture $fixture) {}

            public function record(string $actorId, string $action): void
            {
                $this->fixture->journalInTransaction[] = $this->fixture->inTransaction;
                $this->inner->record($actorId, $action);
            }
        };

        $this->games = new RecordingPairingGames();
        $this->service = new TournamentService(
            $this->repository,
            new Access($this->session, $counting),
            $counting,
            $journal,
            $this->clock,
            $transactions,
            new TournamentViews(),
            $this->games,
        );
    }

    public function as(?string $memberId): TournamentService
    {
        if ($memberId === null) {
            $this->session->logOut();
        } else {
            $this->session->logIn($memberId);
        }

        return $this->service;
    }

    /**
     * Crée un tournoi en root, y inscrit les joueurs donnés et rend son identifiant.
     *
     * @param list<string> $players
     */
    public function tournament(string $name = 'Open', string $format = 'round-robin', array $players = [], ?int $rounds = null): string
    {
        $id = (string) $this->as(self::ROOT)->create($name, '', $format, ['base' => 600, 'inc' => 0], $rounds)['id'];
        foreach ($players as $player) {
            $this->as($player)->register($id);
        }

        return $id;
    }

    /** @param list<string> $players */
    public function started(string $name = 'Open', string $format = 'round-robin', array $players = [self::ALICE, self::BOB], ?int $rounds = null): string
    {
        $id = $this->tournament($name, $format, $players, $rounds);
        $this->as(self::ROOT)->start($id);

        return $id;
    }
}
