<?php

declare(strict_types=1);

namespace LeCercle\Tests\Unit\Game;

use LeCercle\Chess\Ai\RandomSource;
use LeCercle\Game\ChessRules;
use LeCercle\Game\GameEndListener;
use LeCercle\Game\GameService;
use LeCercle\Game\GameViews;
use LeCercle\Shared\Access;
use LeCercle\Shared\AdminJournal;
use LeCercle\Shared\Members;
use LeCercle\Shared\TransactionRunner;
use LeCercle\Tests\Support\FakeSession;
use LeCercle\Tests\Support\FixedClock;
use LeCercle\Tests\Support\ImmediateTransactionRunner;
use LeCercle\Tests\Support\InMemoryGameRepository;
use LeCercle\Tests\Support\InMemoryMembers;
use LeCercle\Tests\Support\MemberFactory;
use LeCercle\Tests\Support\RecordingAdminJournal;
use LeCercle\Tests\Support\RecordingGameEndListener;
use LeCercle\Tests\Support\ScriptedComputerPlayers;
use LeCercle\Tests\Unit\Chess\Ai\FixedRandom;

/** Montage commun des tests de GameService : alice, bob, carol et un compte suspendu, horloge figée, dépôt en mémoire. */
trait GameServiceFixture
{
    private const ALICE = 'u000000000001';
    private const BOB = 'u000000000002';
    private const CAROL = 'u000000000003';
    private const SUSPENDED = 'u000000000004';
    private const ROOT = 'u000000000009';

    private \DateTimeImmutable $t0;
    private FixedClock $clock;
    private FakeSession $session;
    private Members $members;
    private ChessRules $rules;
    private InMemoryGameRepository $games;
    private RecordingAdminJournal $journal;

    private function setUpFixture(): void
    {
        $this->t0 = new \DateTimeImmutable('2026-11-12 10:00:00.000', new \DateTimeZone('UTC'));
        $this->clock = new FixedClock($this->t0);
        $this->session = new FakeSession();
        $this->members = new InMemoryMembers(
            MemberFactory::user(self::ALICE, 'alice'),
            MemberFactory::user(self::BOB, 'bob'),
            MemberFactory::user(self::CAROL, 'carol'),
            MemberFactory::suspended(self::SUSPENDED, 'dave'),
            MemberFactory::admin(self::ROOT, 'root'),
        );
        $this->rules = ChessRules::standard();
        $this->games = new InMemoryGameRepository($this->rules);
        $this->journal = new RecordingAdminJournal();
    }

    private function service(
        ?ScriptedComputerPlayers $computer = null,
        ?RandomSource $random = null,
        ?TransactionRunner $transactions = null,
        ?Members $members = null,
        ?AdminJournal $journal = null,
        ?GameEndListener $listener = null,
    ): GameService {
        $members ??= $this->members;

        return new GameService(
            $this->games,
            new Access($this->session, $members),
            $journal ?? $this->journal,
            $this->clock,
            $transactions ?? new ImmediateTransactionRunner(),
            $this->rules,
            $computer ?? new ScriptedComputerPlayers($this->rules),
            $random ?? new FixedRandom(0.0),
            new GameViews($members, $this->rules),
            $listener ?? new RecordingGameEndListener(),
        );
    }

    private function as(?string $userId): void
    {
        if ($userId === null) {
            $this->session->logOut();
        } else {
            $this->session->logIn($userId);
        }
    }

    /**
     * Partie en ligne alice (blancs) contre bob, lancée à l'instant de l'horloge ; renvoie son identifiant.
     *
     * @param array<mixed>|null $tc
     */
    private function onlineGame(GameService $service, ?array $tc = ['base' => 300, 'inc' => 0]): string
    {
        $this->as(self::ALICE);
        $id = $service->create('online', null, $tc, 'w')['id'];
        $this->as(self::BOB);
        $service->join($id);
        $this->as(self::ALICE);

        return $id;
    }
}
