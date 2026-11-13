<?php

declare(strict_types=1);

namespace LeCercle\Tests\Unit\User;

use LeCercle\Shared\Millis;
use LeCercle\Tests\Support\FixedGameStats;
use LeCercle\Tests\Support\MemberFactory;
use LeCercle\User\Stats;
use LeCercle\User\User;
use LeCercle\User\UserViews;
use PHPUnit\Framework\TestCase;

final class UserViewsTest extends TestCase
{
    private function alice(): User
    {
        return User::registered(
            'u000000000001',
            'alice',
            'alice@x.fr',
            'plain:secret1',
            \LeCercle\Shared\Role::User,
            new \DateTimeImmutable('2026-03-04 05:06:07.089'),
        );
    }

    public function testEmailShownToTheUserHimself(): void
    {
        self::assertSame('alice@x.fr', (new UserViews(new FixedGameStats()))->user($this->alice(), MemberFactory::user())['email']);
    }

    public function testEmailHiddenFromOtherUsersAndAnonymous(): void
    {
        $views = new UserViews(new FixedGameStats());

        self::assertNull($views->user($this->alice(), MemberFactory::suspended())['email']);
        self::assertNull($views->user($this->alice(), null)['email']);
    }

    public function testEmailShownToAdmin(): void
    {
        self::assertSame('alice@x.fr', (new UserViews(new FixedGameStats()))->user($this->alice(), MemberFactory::admin())['email']);
    }

    public function testShapeAndValues(): void
    {
        $view = (new UserViews(new FixedGameStats(['u000000000001' => new Stats(3, 2, 1)])))->user($this->alice()->withSuspended(true), null);

        self::assertSame(['id', 'pseudo', 'email', 'provider', 'role', 'banned', 'stats', 'createdAt'], array_keys($view));
        self::assertSame('local', $view['provider']);
        self::assertSame('user', $view['role']);
        self::assertTrue($view['banned']);
        self::assertSame(['w' => 3, 'l' => 2, 'd' => 1], $view['stats']);
        self::assertSame(Millis::of(new \DateTimeImmutable('2026-03-04 05:06:07.089')), $view['createdAt']);
    }
}
