<?php

declare(strict_types=1);

namespace LeCercle\Tests\Unit\User;

use LeCercle\Shared\Access;
use LeCercle\Shared\ForbiddenException;
use LeCercle\Shared\Role;
use LeCercle\Tests\Support\FakeSession;
use LeCercle\Tests\Support\FixedClock;
use LeCercle\Tests\Support\InMemoryUserRepository;
use LeCercle\Tests\Support\UserRepositoryMembers;
use LeCercle\User\PresenceService;
use LeCercle\User\User;
use PHPUnit\Framework\TestCase;

final class PresenceServiceTest extends TestCase
{
    private const ALICE = 'u000000000001';
    private const BOB = 'u000000000002';

    private InMemoryUserRepository $users;
    private FakeSession $session;
    private FixedClock $clock;
    private PresenceService $service;

    protected function setUp(): void
    {
        $created = new \DateTimeImmutable('2026-05-06 07:00:00', new \DateTimeZone('UTC'));
        $this->users = new InMemoryUserRepository();
        $this->users->add(User::registered(self::ALICE, 'alice', 'alice@b.fr', 'h', Role::User, $created));
        $this->users->add(User::registered(self::BOB, 'bob', 'bob@b.fr', 'h', Role::User, $created));
        $this->session = new FakeSession();
        $this->clock = new FixedClock(new \DateTimeImmutable('2026-05-06 08:00:00', new \DateTimeZone('UTC')));
        $this->service = new PresenceService(
            $this->users,
            new Access($this->session, new UserRepositoryMembers($this->users)),
            $this->clock,
        );
    }

    public function testHeartbeatMakesTheMemberOnline(): void
    {
        $this->session->logIn(self::ALICE);
        $this->service->heartbeat();

        $this->clock->advanceMs(69_000);
        self::assertSame([['id' => self::ALICE, 'pseudo' => 'alice']], $this->service->online());

        $this->clock->advanceMs(2_000);
        self::assertSame([], $this->service->online());
    }

    public function testWindowEdgeIsStillOnline(): void
    {
        $this->session->logIn(self::ALICE);
        $this->service->heartbeat();

        $this->clock->advanceMs(PresenceService::ONLINE_WINDOW_SECONDS * 1000);
        self::assertCount(1, $this->service->online());

        $this->clock->advanceMs(1);
        self::assertSame([], $this->service->online());
    }

    public function testNewHeartbeatExtendsPresence(): void
    {
        $this->session->logIn(self::ALICE);
        $this->service->heartbeat();
        $this->clock->advanceMs(60_000);
        $this->service->heartbeat();
        $this->clock->advanceMs(60_000);

        self::assertCount(1, $this->service->online());
    }

    public function testOnlyTheCallerIsTouched(): void
    {
        $this->session->logIn(self::ALICE);
        $this->service->heartbeat();

        self::assertSame(['alice'], array_column($this->service->online(), 'pseudo'));
    }

    public function testOnlineIsSortedByPseudo(): void
    {
        $this->session->logIn(self::BOB);
        $this->service->heartbeat();
        $this->session->logIn(self::ALICE);
        $this->service->heartbeat();

        self::assertSame(
            [['id' => self::ALICE, 'pseudo' => 'alice'], ['id' => self::BOB, 'pseudo' => 'bob']],
            $this->service->online(),
        );
    }

    public function testHeartbeatNeedsAnAccount(): void
    {
        $this->expectException(ForbiddenException::class);
        $this->expectExceptionMessage(Access::LOGIN_REQUIRED);
        $this->service->heartbeat();
    }

    public function testSuspendedMemberCannotHeartbeat(): void
    {
        $alice = $this->users->find(self::ALICE);
        self::assertNotNull($alice);
        $this->users->save($alice->withSuspended(true));
        $this->session->logIn(self::ALICE);
        try {
            $this->service->heartbeat();
            self::fail('ForbiddenException attendue');
        } catch (ForbiddenException $e) {
            self::assertSame(Access::SUSPENDED, $e->getMessage());
        }
        self::assertSame([], $this->service->online());
    }

    public function testOnlineIsPublic(): void
    {
        $this->session->logIn(self::ALICE);
        $this->service->heartbeat();
        $this->session->logOut();

        self::assertSame([['id' => self::ALICE, 'pseudo' => 'alice']], $this->service->online());
    }
}
