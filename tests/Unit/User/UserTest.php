<?php

declare(strict_types=1);

namespace LeCercle\Tests\Unit\User;

use LeCercle\Shared\Role;
use LeCercle\User\Provider;
use LeCercle\User\User;
use PHPUnit\Framework\TestCase;

final class UserTest extends TestCase
{
    private \DateTimeImmutable $t0;

    protected function setUp(): void
    {
        $this->t0 = new \DateTimeImmutable('2026-11-12 10:00:00');
    }

    public function testRegistered(): void
    {
        $u = User::registered('u1', 'alice', 'a@x.fr', 'h', Role::User, $this->t0);
        self::assertSame(Provider::Local, $u->provider);
        self::assertFalse($u->suspended);
        self::assertTrue($u->canLogInWithPassword());
        self::assertSame($this->t0, $u->createdAt);
        self::assertNull($u->lastSeenAt);
    }

    public function testGuest(): void
    {
        $u = User::guest('u2', 'Invite_ab12cd34', $this->t0);
        self::assertSame(Provider::Guest, $u->provider);
        self::assertSame(Role::User, $u->role);
        self::assertNull($u->email);
        self::assertNull($u->passwordHash);
        self::assertFalse($u->canLogInWithPassword());
    }

    public function testLocalWithoutHashCannotLogIn(): void
    {
        $u = new User('u3', 'bob', 'b@x.fr', Provider::Local, Role::User, null, false, $this->t0);
        self::assertFalse($u->canLogInWithPassword());
    }

    public function testWithersReturnCopies(): void
    {
        $u = User::registered('u1', 'alice', 'a@x.fr', 'h', Role::User, $this->t0);
        self::assertSame(Role::Admin, $u->withRole(Role::Admin)->role);
        self::assertSame(Role::User, $u->role);
        self::assertTrue($u->withSuspended(true)->suspended);
        self::assertFalse($u->suspended);
        self::assertSame('h2', $u->withPasswordHash('h2')->passwordHash);
        self::assertSame('h', $u->passwordHash);
    }
}
