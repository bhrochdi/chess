<?php

declare(strict_types=1);

namespace LeCercle\Tests\Integration\Infra\User;

use LeCercle\Infra\Db\PdoFactory;
use LeCercle\Infra\User\PdoMembers;
use LeCercle\Infra\User\PdoUserRepository;
use LeCercle\Shared\ConflictException;
use LeCercle\Shared\Role;
use LeCercle\Tests\Integration\DatabaseTestCase;
use LeCercle\Tests\Integration\TestDatabase;
use LeCercle\Tests\Support\UserRepositoryMembers;
use LeCercle\User\Provider;
use LeCercle\User\User;
use LeCercle\User\UserRepository;

final class PdoUserRepositoryTest extends DatabaseTestCase
{
    private const T0 = '2026-06-01 12:00:00.250';

    private function repo(): UserRepository
    {
        return new PdoUserRepository(self::pdo());
    }

    private function alice(string $id = 'u000000000001', Role $role = Role::User): User
    {
        return new User(
            $id,
            'Alice',
            'alice@x.fr',
            Provider::Local,
            $role,
            '$2y$10$hashhashhashhashhashhash',
            false,
            new \DateTimeImmutable('2026-03-04 05:06:07.123', new \DateTimeZone('UTC')),
        );
    }

    private function t0(): \DateTimeImmutable
    {
        return new \DateTimeImmutable(self::T0, new \DateTimeZone('UTC'));
    }

    public function testMigrationAddsLastSeen(): void
    {
        $stmt = self::pdo()->query(
            "SELECT data_type FROM information_schema.columns
             WHERE table_schema = DATABASE() AND table_name = 'users' AND column_name = 'last_seen_at'",
        );
        self::assertNotFalse($stmt);
        self::assertSame('datetime', strtolower((string) $stmt->fetchColumn()));

        $names = self::pdo()->query('SELECT name FROM schema_migrations');
        self::assertNotFalse($names);
        self::assertContains('400-presence', $names->fetchAll(\PDO::FETCH_COLUMN));
    }

    public function testRoundTrip(): void
    {
        $repo = $this->repo();
        $alice = $this->alice(role: Role::Admin);
        $repo->add($alice);

        $found = $repo->find('u000000000001');

        self::assertNotNull($found);
        self::assertSame('Alice', $found->pseudo);
        self::assertSame('alice@x.fr', $found->email);
        self::assertSame(Provider::Local, $found->provider);
        self::assertSame(Role::Admin, $found->role);
        self::assertSame('$2y$10$hashhashhashhashhashhash', $found->passwordHash);
        self::assertFalse($found->suspended);
        self::assertSame('2026-03-04 05:06:07.123', $found->createdAt->format('Y-m-d H:i:s.v'));
        self::assertNull($found->lastSeenAt);

        $repo->save($found->withSuspended(true)->withRole(Role::User)->withPasswordHash('nouveau'));
        $after = $repo->find('u000000000001');

        self::assertNotNull($after);
        self::assertTrue($after->suspended);
        self::assertSame(Role::User, $after->role);
        self::assertSame('nouveau', $after->passwordHash);
        self::assertNull($repo->find('u0000000000ff'));
    }

    public function testGuestRoundTrip(): void
    {
        $repo = $this->repo();
        $now = $this->t0();
        $repo->add(User::guest('u000000000001', 'Invite_aaaaaaaa', $now));
        $repo->add(User::guest('u000000000002', 'Invite_bbbbbbbb', $now));

        $guest = $repo->find('u000000000001');

        self::assertNotNull($guest);
        self::assertSame(Provider::Guest, $guest->provider);
        self::assertNull($guest->email);
        self::assertNull($guest->passwordHash);
        self::assertNotNull($repo->find('u000000000002'));
    }

    public function testFindByLogin(): void
    {
        $repo = $this->repo();
        $repo->add($this->alice());

        self::assertSame('u000000000001', $repo->findByLogin('alice')?->id);
        self::assertSame('u000000000001', $repo->findByLogin('alice@x.fr')?->id);
        self::assertNull($repo->findByLogin('nobody'));
    }

    public function testTakenChecksAreCaseInsensitive(): void
    {
        $repo = $this->repo();
        $repo->add($this->alice());

        self::assertTrue($repo->pseudoTaken('ALICE'));
        self::assertFalse($repo->pseudoTaken('bob'));
        self::assertTrue($repo->emailTaken('ALICE@X.FR'));
        self::assertFalse($repo->emailTaken('bob@x.fr'));
    }

    public function testDuplicatePseudoIsAConflict(): void
    {
        $repo = $this->repo();
        $repo->add($this->alice());
        $other = new User('u000000000002', 'alice', 'autre@x.fr', Provider::Local, Role::User, 'h', false, $this->t0());

        $this->expectException(ConflictException::class);
        $this->expectExceptionMessage('Ce pseudo est déjà pris.');
        $repo->add($other);
    }

    public function testDuplicateEmailIsAConflict(): void
    {
        $repo = $this->repo();
        $repo->add($this->alice());
        $other = new User('u000000000002', 'Bob', 'ALICE@x.fr', Provider::Local, Role::User, 'h', false, $this->t0());

        $this->expectException(ConflictException::class);
        $this->expectExceptionMessage('Un compte existe déjà avec cet e-mail.');
        $repo->add($other);
    }

    public function testDuplicateEmailContainingAKeyNameIsStillAnEmailConflict(): void
    {
        $repo = $this->repo();
        $first = new User('u000000000001', 'Alice', 'uniq_pseudo@x.fr', Provider::Local, Role::User, 'h', false, $this->t0());
        $repo->add($first);
        $other = new User('u000000000002', 'Bob', 'uniq_pseudo@x.fr', Provider::Local, Role::User, 'h', false, $this->t0());

        $this->expectException(ConflictException::class);
        $this->expectExceptionMessage('Un compte existe déjà avec cet e-mail.');
        $repo->add($other);
    }

    public function testDuplicateIdIsNotMaskedAsAConflict(): void
    {
        $repo = $this->repo();
        $repo->add($this->alice());
        $other = new User('u000000000001', 'Bob', 'bob@x.fr', Provider::Local, Role::User, 'h', false, $this->t0());

        $this->expectException(\PDOException::class);
        $repo->add($other);
    }

    public function testCountsAndDelete(): void
    {
        $repo = $this->repo();
        $repo->add($this->alice('u000000000001', Role::Admin));
        $repo->add(new User('u000000000002', 'Bob', 'bob@x.fr', Provider::Local, Role::User, 'h', false, $this->t0()));

        self::assertSame(2, \count($repo->all()));
        self::assertSame(1, $repo->countAdmins());

        $repo->delete('u000000000002');

        self::assertSame(1, \count($repo->all()));
        self::assertNull($repo->find('u000000000002'));
    }

    public function testCountAdminsIgnoresSuspendedAdmins(): void
    {
        $repo = $this->repo();
        $repo->add($this->alice('u000000000001', Role::Admin)->withSuspended(true));
        $repo->add(new User('u000000000002', 'Bob', 'bob@x.fr', Provider::Local, Role::Admin, 'h', false, $this->t0()));
        $repo->add(new User('u000000000003', 'Cleo', 'cleo@x.fr', Provider::Local, Role::User, 'h', false, $this->t0()));

        self::assertSame(1, $repo->countAdmins());
        self::assertSame(3, \count($repo->all()));
    }

    public function testAllIsOldestFirst(): void
    {
        $repo = $this->repo();
        $repo->add(new User('u000000000001', 'Zed', 'z@x.fr', Provider::Local, Role::User, 'h', false, new \DateTimeImmutable('2026-01-02', new \DateTimeZone('UTC'))));
        $repo->add(new User('u000000000002', 'Ann', 'a@x.fr', Provider::Local, Role::User, 'h', false, new \DateTimeImmutable('2026-01-01', new \DateTimeZone('UTC'))));

        self::assertSame(['Ann', 'Zed'], array_map(static fn(User $u): string => $u->pseudo, $repo->all()));
    }

    public function testPresence(): void
    {
        $repo = $this->repo();
        $repo->add($this->alice());
        $repo->add(new User('u000000000002', 'Abel', 'abel@x.fr', Provider::Local, Role::User, 'h', false, $this->t0()));
        $repo->add(new User('u000000000003', 'Never', 'n@x.fr', Provider::Local, Role::User, 'h', false, $this->t0()));
        $t0 = $this->t0();
        $repo->touch('u000000000001', $t0);
        $repo->touch('u000000000002', $t0);

        $seen = $repo->seenSince($t0->modify('-1 second'));
        self::assertSame(['Abel', 'Alice'], array_map(static fn(User $u): string => $u->pseudo, $seen));
        self::assertSame(self::T0, $seen[0]->lastSeenAt?->format('Y-m-d H:i:s.v'));
        self::assertCount(2, $repo->seenSince($t0), 'la borne est incluse');
        self::assertSame([], $repo->seenSince($t0->modify('+1 second')));
        self::assertNull($repo->find('u000000000003')?->lastSeenAt);
    }

    public function testInactiveGuestIds(): void
    {
        $repo = $this->repo();
        $t0 = $this->t0();
        $repo->add(User::guest('u0000000000g1', 'Invite_seenold', $t0->modify('-10 days')));
        $repo->touch('u0000000000g1', $t0->modify('-1 second'));
        $repo->add(User::guest('u0000000000g2', 'Invite_seennew', $t0->modify('-10 days')));
        $repo->touch('u0000000000g2', $t0);
        $repo->add(User::guest('u0000000000g3', 'Invite_neverold', $t0->modify('-1 second')));
        $repo->add(User::guest('u0000000000g4', 'Invite_nevernew', $t0));
        $repo->add(new User('u000000000001', 'Ancien', 'a@x.fr', Provider::Local, Role::User, 'h', false, $t0->modify('-10 days')));

        self::assertSame(['u0000000000g1', 'u0000000000g3'], $repo->inactiveGuestIds($t0));
    }

    public function testTouchWritesUtcWhateverTheTimezone(): void
    {
        $repo = $this->repo();
        $repo->add($this->alice());
        $repo->touch('u000000000001', new \DateTimeImmutable('2026-06-01 14:00:00.250', new \DateTimeZone('Europe/Paris')));

        $stmt = self::pdo()->query('SELECT last_seen_at FROM users');
        self::assertNotFalse($stmt);
        self::assertSame(self::T0, $stmt->fetchColumn());
    }

    public function testMembersSeeTheSameRows(): void
    {
        $repo = $this->repo();
        $repo->add(User::guest('u000000000001', 'Invite_aaaaaaaa', $this->t0()));
        $repo->add($this->alice('u000000000002', Role::Admin));

        $member = (new PdoMembers(self::pdo()))->find('u000000000001');

        self::assertNotNull($member);
        self::assertTrue($member->guest);
        self::assertSame(Role::Admin, (new PdoMembers(self::pdo()))->find('u000000000002')?->role);
    }

    public function testUnknownProviderAndRole(): void
    {
        $stmt = self::pdo()->prepare(
            'INSERT INTO users (id, pseudo, email, provider, role, pass_hash, banned, created_at)
             VALUES (?, ?, ?, ?, ?, NULL, 0, ?)',
        );
        $stmt->execute(['u000000000001', 'carol', 'c@x.fr', 'facebook', 'moderator', 1794477601500]);

        $user = $this->repo()->find('u000000000001');

        self::assertNotNull($user);
        self::assertSame(Provider::Facebook, $user->provider);
        self::assertSame(Role::User, $user->role);
        self::assertSame('2026-11-12 10:00:01.500', $user->createdAt->format('Y-m-d H:i:s.v'));
    }

    public function testNextIdShape(): void
    {
        $repo = $this->repo();

        self::assertMatchesRegularExpression('/\Au[0-9a-f]{12}\z/', $repo->nextId());
        self::assertNotSame($repo->nextId(), $repo->nextId());
    }

    public function testFindManyIsDedupedKeyedAndSkipsTheQueryWhenEmpty(): void
    {
        $repo = new PdoUserRepository(self::pdo());
        $repo->add($this->alice());

        // Contrat Members : même comportement que PdoMembers sur le même PDO.
        $members = new UserRepositoryMembers($repo);
        $found = $members->findMany([1 => 'u000000000001', 3 => 'u000000000001', 4 => 'u0000000000ff']);

        self::assertSame(['u000000000001'], array_keys($found));
        self::assertSame([], $members->findMany([]));
    }

    public function testCountAdminsTakesALockInsideTheCallersTransaction(): void
    {
        $this->repo()->add($this->alice('u000000000001', Role::Admin));
        $other = PdoFactory::create(TestDatabase::config());
        $other->exec('SET SESSION innodb_lock_wait_timeout = 1');
        $pdo = self::pdo();
        $pdo->beginTransaction();
        try {
            $this->repo()->countAdmins();
            $this->expectException(\PDOException::class);
            $this->expectExceptionMessageMatches('/Lock wait timeout/');
            $other->exec("UPDATE users SET banned = 1 WHERE id = 'u000000000001'");
        } finally {
            $pdo->rollBack();
        }
    }

    public function testCountRegisteredIgnoresGuests(): void
    {
        $repo = $this->repo();
        $repo->add(User::guest('u000000000001', 'Invite_aaaaaaaa', $this->t0()));

        self::assertSame(0, $repo->countRegistered());

        $repo->add($this->alice('u000000000002'));
        self::assertSame(1, $repo->countRegistered());
    }

    public function testCountRegisteredTakesALockEvenWhenOnlyGuestsExist(): void
    {
        $this->repo()->add(User::guest('u000000000001', 'Invite_aaaaaaaa', $this->t0()));
        $other = PdoFactory::create(TestDatabase::config());
        $other->exec('SET SESSION innodb_lock_wait_timeout = 1');
        $pdo = self::pdo();
        $pdo->beginTransaction();
        try {
            $this->repo()->countRegistered();
            $this->expectException(\PDOException::class);
            $this->expectExceptionMessageMatches('/Lock wait timeout/');
            // Aucune ligne ne correspond au filtre : seul le verrou du parcours (next-key) bloque cette insertion.
            $other->exec(
                "INSERT INTO users (id, pseudo, email, provider, role, banned, created_at)
                 VALUES ('u0000000000aa', 'racer', 'r@x.fr', 'local', 'admin', 0, 1)",
            );
        } finally {
            $pdo->rollBack();
        }
    }
}
