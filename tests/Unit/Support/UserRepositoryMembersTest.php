<?php

declare(strict_types=1);

namespace LeCercle\Tests\Unit\Support;

use LeCercle\Tests\Support\InMemoryUserRepository;
use LeCercle\Tests\Support\UserRepositoryMembers;
use LeCercle\User\User;
use PHPUnit\Framework\TestCase;

final class UserRepositoryMembersTest extends TestCase
{
    private function members(): UserRepositoryMembers
    {
        $repo = new InMemoryUserRepository();
        $now = new \DateTimeImmutable('2026-01-01 00:00:00');
        $repo->add(User::registered('u1', 'Alice', 'a@x.fr', 'h', \LeCercle\Shared\Role::Admin, $now));
        $repo->add(User::guest('u2', 'Invite_ab12cd34', $now)->withSuspended(true));

        return new UserRepositoryMembers($repo);
    }

    public function testFindMapsUserToMember(): void
    {
        $alice = $this->members()->find('u1');
        $guest = $this->members()->find('u2');

        self::assertTrue($alice?->isAdmin());
        self::assertFalse($alice->guest);
        self::assertTrue($guest?->guest);
        self::assertTrue($guest->suspended);
        self::assertNull($this->members()->find('nope'));
    }

    public function testFindByPseudoIgnoresCase(): void
    {
        self::assertSame('u1', $this->members()->findByPseudo('ALICE')?->id);
        self::assertNull($this->members()->findByPseudo('nobody'));
    }

    public function testFindManyDeduplicatesAndKeysByIdWithKeyedInput(): void
    {
        $found = $this->members()->findMany([2 => 'u1', 5 => 'u1', 7 => 'nope', 9 => 'u2']);

        self::assertSame(['u1', 'u2'], array_keys($found));
    }
}
