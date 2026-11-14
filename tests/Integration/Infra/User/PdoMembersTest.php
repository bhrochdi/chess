<?php

declare(strict_types=1);

namespace LeCercle\Tests\Integration\Infra\User;

use LeCercle\Infra\User\PdoMembers;
use LeCercle\Shared\Role;
use LeCercle\Tests\Integration\DatabaseTestCase;

final class PdoMembersTest extends DatabaseTestCase
{
    private function insert(string $id, string $pseudo, string $provider = 'local', string $role = 'user', int $banned = 0): void
    {
        $stmt = self::pdo()->prepare(
            'INSERT INTO users (id, pseudo, email, provider, role, pass_hash, banned, created_at)
             VALUES (?, ?, ?, ?, ?, NULL, ?, ?)',
        );
        $stmt->execute([$id, $pseudo, $id . '@x.fr', $provider, $role, $banned, 1794477601500]);
    }

    public function testFindMapsColumns(): void
    {
        $this->insert('u000000000001', 'alice', 'local', 'admin');

        $member = (new PdoMembers(self::pdo()))->find('u000000000001');

        self::assertNotNull($member);
        self::assertSame('u000000000001', $member->id);
        self::assertSame('alice', $member->pseudo);
        self::assertSame(Role::Admin, $member->role);
        self::assertFalse($member->suspended);
        self::assertFalse($member->guest);
        self::assertNull((new PdoMembers(self::pdo()))->find('u0000000000ff'));
    }

    public function testGuestAndSuspended(): void
    {
        $this->insert('u000000000003', 'Invite_ab12', 'guest', 'user', 1);

        $member = (new PdoMembers(self::pdo()))->find('u000000000003');

        self::assertNotNull($member);
        self::assertTrue($member->guest);
        self::assertTrue($member->suspended);
    }

    public function testUnknownRoleFallsBackToUser(): void
    {
        $this->insert('u000000000004', 'carol', 'local', 'moderator');

        self::assertSame(Role::User, (new PdoMembers(self::pdo()))->find('u000000000004')?->role);
    }

    public function testFindByPseudoIsCaseInsensitive(): void
    {
        $this->insert('u000000000001', 'alice');
        $members = new PdoMembers(self::pdo());

        self::assertSame('u000000000001', $members->findByPseudo('ALICE')?->id);
        self::assertNull($members->findByPseudo('nobody'));
    }

    public function testFindManyReturnsOnlyKnownIds(): void
    {
        $this->insert('u000000000001', 'alice');

        $found = (new PdoMembers(self::pdo()))->findMany(['u000000000001', 'u0000000000ff']);

        self::assertSame(['u000000000001'], array_keys($found));
    }

    public function testFindManyAcceptsDuplicatedAndKeyedIds(): void
    {
        $this->insert('u000000000001', 'alice');

        $found = (new PdoMembers(self::pdo()))->findMany([1 => 'u000000000001', 3 => 'u000000000001']);

        self::assertSame(['u000000000001'], array_keys($found));
    }

    public function testFindManyWithNoIdIsEmptyWithoutQuery(): void
    {
        self::assertSame([], (new PdoMembers(self::pdo()))->findMany([]));
    }
}
