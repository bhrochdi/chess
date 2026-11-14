<?php

declare(strict_types=1);

namespace LeCercle\Tests\Unit\User;

use LeCercle\Shared\Access;
use LeCercle\Shared\ConflictException;
use LeCercle\Shared\ForbiddenException;
use LeCercle\Shared\NotFoundException;
use LeCercle\Shared\Role;
use LeCercle\Shared\ValidationException;
use LeCercle\Tests\Support\FixedGameStats;
use LeCercle\Tests\Support\FakeSession;
use LeCercle\Tests\Support\FixedClock;
use LeCercle\Tests\Support\FlaggingTransactionRunner;
use LeCercle\Tests\Support\ImmediateTransactionRunner;
use LeCercle\Tests\Support\InMemoryUserRepository;
use LeCercle\Tests\Support\RecordingAdminJournal;
use LeCercle\Tests\Support\TransactionAwareJournal;
use LeCercle\Tests\Support\UserRepositoryMembers;
use LeCercle\User\Stats;
use LeCercle\User\User;
use LeCercle\User\UserAdminService;
use LeCercle\User\UserViews;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class UserAdminServiceTest extends TestCase
{
    private const ROOT = 'u000000000001';
    private const ALICE = 'u000000000002';
    private const BOB = 'u000000000003';

    private InMemoryUserRepository $users;
    private FakeSession $session;
    private RecordingAdminJournal $journal;
    private UserAdminService $service;
    private FixedClock $clock;

    protected function setUp(): void
    {
        $now = new \DateTimeImmutable('2026-05-06 07:08:09', new \DateTimeZone('UTC'));
        $this->users = new InMemoryUserRepository();
        $this->users->add(User::registered(self::ROOT, 'root', 'root@b.fr', 'h', Role::Admin, $now));
        $this->users->add(User::registered(self::ALICE, 'alice', 'alice@b.fr', 'h', Role::User, $now->modify('+1 second')));
        $this->users->add(User::registered(self::BOB, 'bob', 'bob@b.fr', 'h', Role::User, $now->modify('+2 seconds'))
            ->withSuspended(true));
        $this->session = new FakeSession();
        $this->session->logIn(self::ROOT);
        $this->journal = new RecordingAdminJournal();
        $this->clock = new FixedClock($now->modify('+60 days'));
        $this->service = new UserAdminService(
            $this->users,
            new Access($this->session, new UserRepositoryMembers($this->users)),
            $this->journal,
            new ImmediateTransactionRunner(),
            new UserViews(new FixedGameStats()),
            $this->clock,
        );
    }

    public function testListIsAdminOnly(): void
    {
        $this->session->logIn(self::ALICE);
        try {
            $this->service->list();
            self::fail('Une exception était attendue.');
        } catch (ForbiddenException $e) {
            self::assertSame('Action réservée aux administrateurs.', $e->getMessage());
        }

        $this->session->logIn(self::ROOT);
        $list = $this->service->list();
        self::assertCount(3, $list);
        self::assertSame(['root@b.fr', 'alice@b.fr', 'bob@b.fr'], array_column($list, 'email'));
        self::assertArrayNotHasKey('passwordHash', $list[0]);
    }

    public function testBanAndUnban(): void
    {
        $this->service->update(self::ALICE, 'ban');
        self::assertTrue($this->users->find(self::ALICE)?->suspended);
        $this->service->update(self::ALICE, 'unban');
        self::assertFalse($this->users->find(self::ALICE)->suspended);
        self::assertSame([
            ['actor' => self::ROOT, 'action' => 'Suspension de alice'],
            ['actor' => self::ROOT, 'action' => 'Réactivation de alice'],
        ], $this->journal->entries);
    }

    public function testPromoteAndDemote(): void
    {
        $this->service->update(self::ALICE, 'promote');
        self::assertSame(Role::Admin, $this->users->find(self::ALICE)?->role);
        $this->service->update(self::ALICE, 'demote');
        self::assertSame(Role::User, $this->users->find(self::ALICE)->role);
        self::assertSame(
            ['Rôle de alice → admin', 'Rôle de alice → user'],
            array_column($this->journal->entries, 'action'),
        );
    }

    public function testGuestCannotBePromoted(): void
    {
        $now = new \DateTimeImmutable('2026-05-06 07:08:09', new \DateTimeZone('UTC'));
        $this->users->add(User::guest('u0000000000aa', 'Invite_ab12cd34', $now));

        $this->expectRefusal(ConflictException::class, 'Un compte invité ne peut pas devenir administrateur.', fn() => $this->service->update('u0000000000aa', 'promote'));
        self::assertSame(Role::User, $this->users->find('u0000000000aa')?->role);
        self::assertSame([], $this->journal->entries);
    }

    public function testCannotDemoteTheLastAdmin(): void
    {
        $this->expectRefusal(ConflictException::class, 'Impossible de rétrograder le dernier administrateur.', fn() => $this->service->update(self::ROOT, 'demote'));
        self::assertSame(Role::Admin, $this->users->find(self::ROOT)?->role);
        self::assertSame([], $this->journal->entries);
    }

    public function testCanDemoteAnAdminWhenAnotherRemains(): void
    {
        $this->service->update(self::ALICE, 'promote');
        $this->service->update(self::ROOT, 'demote');
        self::assertSame(Role::User, $this->users->find(self::ROOT)?->role);
    }

    public function testSelfGuards(): void
    {
        $this->expectRefusal(ConflictException::class, 'Impossible de se suspendre soi-même.', fn() => $this->service->update(self::ROOT, 'ban'));
        $this->expectRefusal(ConflictException::class, 'Impossible de supprimer son propre compte.', fn() => $this->service->update(self::ROOT, 'delete'));
        self::assertFalse($this->users->find(self::ROOT)?->suspended);
        self::assertNotNull($this->users->find(self::ROOT));
        self::assertSame([], $this->journal->entries);
    }

    public function testResetStatsIsNoLongerAnOperation(): void
    {
        try {
            $this->service->update(self::ALICE, 'resetstats');
            self::fail('resetstats ne doit plus exister.');
        } catch (ValidationException $e) {
            self::assertSame('Opération inconnue.', $e->getMessage());
        }
        self::assertSame([], $this->journal->entries);
    }

    public function testListLoadsStatsOnceFromFinishedGames(): void
    {
        $stats = new FixedGameStats([self::ALICE => new Stats(3, 1, 2)]);
        $service = new UserAdminService(
            $this->users,
            new Access($this->session, new UserRepositoryMembers($this->users)),
            $this->journal,
            new ImmediateTransactionRunner(),
            new UserViews($stats),
            $this->clock,
        );

        $rows = array_column($service->list(), 'stats', 'id');

        self::assertSame(1, $stats->calls);
        self::assertSame(['w' => 3, 'l' => 1, 'd' => 2], $rows[self::ALICE]);
        self::assertSame(['w' => 0, 'l' => 0, 'd' => 0], $rows[self::BOB]);
    }

    public function testDelete(): void
    {
        $this->service->update(self::ALICE, 'delete');
        self::assertNull($this->users->find(self::ALICE));
        self::assertNotNull($this->users->find(self::BOB));
        self::assertSame([['actor' => self::ROOT, 'action' => 'Suppression du compte alice']], $this->journal->entries);
    }

    public function testUnknownTargetAndOp(): void
    {
        $this->expectRefusal(NotFoundException::class, 'Utilisateur introuvable.', fn() => $this->service->update('u0000000000ff', 'ban'));
        $this->expectRefusal(ValidationException::class, 'Opération inconnue.', fn() => $this->service->update(self::ALICE, 'nuke'));
        self::assertSame([], $this->journal->entries);
    }

    public function testNonAdminCannotUpdate(): void
    {
        $this->session->logIn(self::ALICE);
        $this->expectRefusal(ForbiddenException::class, 'Action réservée aux administrateurs.', fn() => $this->service->update(self::BOB, 'unban'));
        self::assertTrue($this->users->find(self::BOB)?->suspended);
        self::assertSame([], $this->journal->entries);
    }

    public function testSuspendedAdminIsRefused(): void
    {
        $this->users->save($this->users->find(self::ROOT)?->withSuspended(true) ?? throw new \LogicException());
        $this->expectRefusal(ForbiddenException::class, 'Compte suspendu par l’administration.', fn() => $this->service->update(self::ALICE, 'ban'));
        $this->expectRefusal(ForbiddenException::class, 'Compte suspendu par l’administration.', fn() => $this->service->list());
        self::assertFalse($this->users->find(self::ALICE)?->suspended);
    }

    /** @param class-string<\Throwable> $class */
    private function expectRefusal(string $class, string $message, callable $call): void
    {
        try {
            $call();
        } catch (\Throwable $e) {
            self::assertInstanceOf($class, $e);
            self::assertSame($message, $e->getMessage());

            return;
        }
        self::fail('Une exception était attendue : ' . $message);
    }

    /** @return iterable<string, array{string, string}> */
    public static function journaledOperations(): iterable
    {
        yield 'ban' => [self::ALICE, 'ban'];
        yield 'unban' => [self::BOB, 'unban'];
        yield 'promote' => [self::ALICE, 'promote'];
        yield 'demote' => [self::ALICE, 'demote'];
        yield 'delete' => [self::ALICE, 'delete'];
    }

    #[DataProvider('journaledOperations')]
    public function testJournalIsWrittenInsideTheTransaction(string $targetId, string $op): void
    {
        $runner = new FlaggingTransactionRunner();
        $journal = new TransactionAwareJournal($runner);
        $service = new UserAdminService(
            $this->users,
            new Access($this->session, new UserRepositoryMembers($this->users)),
            $journal,
            $runner,
            new UserViews(new FixedGameStats()),
            $this->clock,
        );

        $service->update($targetId, $op);

        self::assertSame([true], $journal->insideTransaction);
    }

    private function addGuest(string $id, int $createdDaysAgo, ?int $seenDaysAgo): void
    {
        $now = $this->clock->now();
        $this->users->add(User::guest($id, 'Invite_' . substr($id, -8), $now->modify("-$createdDaysAgo days")));
        if ($seenDaysAgo !== null) {
            $this->users->touch($id, $now->modify("-$seenDaysAgo days"));
        }
    }

    public function testPurgeGuestsIsAdminOnly(): void
    {
        $this->addGuest('u0000000000g1', 40, null);
        $this->session->logIn(self::ALICE);

        try {
            $this->service->purgeGuests();
            self::fail('Une exception était attendue.');
        } catch (ForbiddenException $e) {
            self::assertSame('Action réservée aux administrateurs.', $e->getMessage());
        }
        self::assertNotNull($this->users->find('u0000000000g1'));
        self::assertSame([], $this->journal->entries);
    }

    public function testPurgeGuestsDeletesGuestsInactiveForThirtyDays(): void
    {
        $this->addGuest('u0000000000g1', 40, 31);
        $this->addGuest('u0000000000g2', 40, 29);
        $this->addGuest('u0000000000g3', 31, null);
        $this->addGuest('u0000000000g4', 29, null);

        self::assertSame(2, $this->service->purgeGuests());

        self::assertNull($this->users->find('u0000000000g1'));
        self::assertNotNull($this->users->find('u0000000000g2'));
        self::assertNull($this->users->find('u0000000000g3'));
        self::assertNotNull($this->users->find('u0000000000g4'));
        // Comptes inscrits jamais vus depuis plus de 30 jours : conservés.
        self::assertCount(3, array_filter([self::ROOT, self::ALICE, self::BOB], fn(string $id): bool => $this->users->find($id) !== null));
        self::assertSame([['actor' => self::ROOT, 'action' => 'Purge de 2 compte(s) invité(s) inactif(s)']], $this->journal->entries);
    }

    public function testPurgeGuestsJournalIsWrittenInsideTheTransaction(): void
    {
        $runner = new FlaggingTransactionRunner();
        $journal = new TransactionAwareJournal($runner);
        $service = new UserAdminService(
            $this->users,
            new Access($this->session, new UserRepositoryMembers($this->users)),
            $journal,
            $runner,
            new UserViews(new FixedGameStats()),
            $this->clock,
        );

        $service->purgeGuests();

        self::assertSame([true], $journal->insideTransaction);
    }
}
