<?php

declare(strict_types=1);

namespace LeCercle\Tests\Unit\Support;

use LeCercle\Shared\Role;
use LeCercle\Tests\Support\InMemoryUserRepository;
use LeCercle\User\User;
use PHPUnit\Framework\TestCase;

final class InMemoryUserRepositoryTest extends TestCase
{
    private function user(string $id, string $pseudo, string $email, Role $role = Role::User): User
    {
        return User::registered($id, $pseudo, $email, 'h', $role, new \DateTimeImmutable('2026-01-01 00:00:00'));
    }

    public function testIdsAreSequential(): void
    {
        $repo = new InMemoryUserRepository();

        self::assertSame('u000000000001', $repo->nextId());
        self::assertSame('u000000000002', $repo->nextId());
    }

    public function testForcedIdsComeFirstThenSequenceResumes(): void
    {
        $repo = new InMemoryUserRepository();
        $repo->forceNextIds('uaaaaaaaaaaaa', 'ubbbbbbbbbbbb');

        self::assertSame('uaaaaaaaaaaaa', $repo->nextId());
        self::assertSame('ubbbbbbbbbbbb', $repo->nextId());
        self::assertSame('u000000000001', $repo->nextId());
    }

    public function testFindByLoginMatchesPseudoOrEmailIgnoringCase(): void
    {
        $repo = new InMemoryUserRepository();
        $repo->add($this->user('u1', 'Alice', 'alice@x.fr'));

        self::assertSame('u1', $repo->findByLogin('alice')?->id);
        self::assertSame('u1', $repo->findByLogin('alice@x.fr')?->id);
        self::assertNull($repo->findByLogin('bob'));
    }

    public function testTakenChecksIgnoreCase(): void
    {
        $repo = new InMemoryUserRepository();
        $repo->add($this->user('u1', 'Alice', 'alice@x.fr'));

        self::assertTrue($repo->pseudoTaken('ALICE'));
        self::assertTrue($repo->emailTaken('ALICE@x.fr'));
        self::assertFalse($repo->pseudoTaken('bob'));
        self::assertFalse($repo->emailTaken('bob@x.fr'));
    }

    public function testCountsSaveAndDelete(): void
    {
        $repo = new InMemoryUserRepository();
        $alice = $this->user('u1', 'alice', 'a@x.fr', Role::Admin);
        $repo->add($alice);
        $repo->add($this->user('u2', 'bob', 'b@x.fr'));

        self::assertSame(2, \count($repo->all()));
        self::assertSame(1, $repo->countAdmins());

        $repo->save($alice->withRole(Role::User));
        self::assertSame(0, $repo->countAdmins());

        $repo->delete('u2');
        self::assertSame(1, \count($repo->all()));
        self::assertNull($repo->find('u2'));
    }

    public function testTouchAndSeenSince(): void
    {
        $repo = new InMemoryUserRepository();
        $repo->add($this->user('u1', 'zoe', 'z@x.fr'));
        $repo->add($this->user('u2', 'alice', 'a@x.fr'));
        $repo->add($this->user('u3', 'bob', 'b@x.fr'));
        $now = new \DateTimeImmutable('2026-06-01 12:00:00');
        $repo->touch('u1', $now);
        $repo->touch('u2', $now);
        $repo->touch('nope', $now);

        self::assertSame(['alice', 'zoe'], array_map(static fn(User $u): string => $u->pseudo, $repo->seenSince($now)));
        self::assertSame([], $repo->seenSince($now->modify('+1 second')));
    }

    public function testCountAdminsIgnoresSuspendedAdmins(): void
    {
        $repo = new InMemoryUserRepository();
        $repo->add($this->user('u1', 'alice', 'a@x.fr', Role::Admin)->withSuspended(true));
        $repo->add($this->user('u2', 'bob', 'b@x.fr', Role::Admin));
        $repo->add($this->user('u3', 'cleo', 'c@x.fr'));

        self::assertSame(1, $repo->countAdmins());
        self::assertSame(3, \count($repo->all()));
    }

    public function testCountRegisteredIgnoresGuests(): void
    {
        $repo = new InMemoryUserRepository();
        $repo->add(User::guest('u1', 'Invite_aaaaaaaa', new \DateTimeImmutable('2026-01-01 00:00:00')));

        self::assertSame(0, $repo->countRegistered());

        $repo->add($this->user('u2', 'alice', 'a@x.fr'));
        self::assertSame(1, $repo->countRegistered());
        self::assertSame(2, \count($repo->all()));
    }
}
