<?php

declare(strict_types=1);

namespace LeCercle\Tests\Unit\Shared;

use LeCercle\Shared\Access;
use LeCercle\Shared\ForbiddenException;
use LeCercle\Shared\Member;
use LeCercle\Shared\Role;
use LeCercle\Tests\Support\FakeSession;
use LeCercle\Tests\Support\InMemoryMembers;
use LeCercle\Tests\Support\MemberFactory;
use PHPUnit\Framework\TestCase;

final class AccessTest extends TestCase
{
    private function access(?string $sessionId, Member ...$members): Access
    {
        return new Access(new FakeSession($sessionId), new InMemoryMembers(...$members));
    }

    public function testViewerIsNullWithoutSession(): void
    {
        self::assertNull($this->access(null, MemberFactory::user())->viewer());
    }

    public function testViewerIsNullWhenSessionNamesADeletedUser(): void
    {
        $access = $this->access('u0000000000ff', MemberFactory::user());

        self::assertNull($access->viewer());
        $this->expectException(ForbiddenException::class);
        $this->expectExceptionMessage('Connexion requise.');
        $access->member();
    }

    public function testMemberReturnsTheLoggedInMember(): void
    {
        $access = $this->access('u000000000001', MemberFactory::user());

        self::assertSame('alice', $access->member()->pseudo);
    }

    public function testSuspendedMemberIsRefused(): void
    {
        $access = $this->access('u000000000002', MemberFactory::suspended());

        self::assertSame('bob', $access->viewer()?->pseudo);
        $this->expectException(ForbiddenException::class);
        $this->expectExceptionMessage('Compte suspendu par l’administration.');
        $access->member();
    }

    public function testAdminRequiresTheAdminRole(): void
    {
        $user = $this->access('u000000000001', MemberFactory::user());
        try {
            $user->admin();
            self::fail('ForbiddenException attendue');
        } catch (ForbiddenException $e) {
            self::assertSame('Action réservée aux administrateurs.', $e->getMessage());
        }

        $admin = $this->access('u000000000009', MemberFactory::admin());
        self::assertSame('u000000000009', $admin->admin()->id);
    }

    public function testSuspendedAdminIsRefused(): void
    {
        $access = $this->access('u9', new Member('u9', 'root', Role::Admin, true, false));

        $this->expectException(ForbiddenException::class);
        $this->expectExceptionMessage('Compte suspendu par l’administration.');
        $access->admin();
    }
}
