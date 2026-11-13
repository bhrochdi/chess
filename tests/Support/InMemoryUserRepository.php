<?php

declare(strict_types=1);

namespace LeCercle\Tests\Support;

use LeCercle\Shared\Role;
use LeCercle\User\Provider;
use LeCercle\User\User;
use LeCercle\User\UserRepository;

/** Double de test : comptes en mémoire, identifiants u000000000001, u000000000002… */
final class InMemoryUserRepository implements UserRepository
{
    /** @var array<string, User> dans l'ordre d'ajout */
    private array $byId = [];

    private int $counter = 0;

    /** @var list<string> */
    private array $forcedIds = [];

    /** Les prochains appels à nextId() renvoient ces identifiants, dans cet ordre (test de nouvelle tentative). */
    public function forceNextIds(string ...$ids): void
    {
        $this->forcedIds = array_values($ids);
    }

    public function nextId(): string
    {
        if ($this->forcedIds !== []) {
            return array_shift($this->forcedIds);
        }

        return \sprintf('u%012d', ++$this->counter);
    }

    public function add(User $user): void
    {
        $this->byId[$user->id] = $user;
    }

    public function save(User $user): void
    {
        if (isset($this->byId[$user->id])) {
            $this->byId[$user->id] = $user;
        }
    }

    public function find(string $id): ?User
    {
        return $this->byId[$id] ?? null;
    }

    public function findByLogin(string $identifier): ?User
    {
        $needle = strtolower($identifier);
        foreach ($this->byId as $user) {
            if (strtolower($user->pseudo) === $needle || ($user->email !== null && strtolower($user->email) === $needle)) {
                return $user;
            }
        }

        return null;
    }

    public function pseudoTaken(string $pseudo): bool
    {
        foreach ($this->byId as $user) {
            if (strtolower($user->pseudo) === strtolower($pseudo)) {
                return true;
            }
        }

        return false;
    }

    public function emailTaken(string $email): bool
    {
        foreach ($this->byId as $user) {
            if ($user->email !== null && strtolower($user->email) === strtolower($email)) {
                return true;
            }
        }

        return false;
    }

    public function countRegistered(): int
    {
        return \count(array_filter($this->byId, static fn(User $u): bool => $u->provider !== Provider::Guest));
    }

    public function countAdmins(): int
    {
        return \count(array_filter($this->byId, static fn(User $u): bool => $u->role === Role::Admin && !$u->suspended));
    }

    public function delete(string $id): void
    {
        unset($this->byId[$id]);
    }

    public function all(): array
    {
        $users = array_values($this->byId);
        usort($users, static fn(User $a, User $b): int => $a->createdAt <=> $b->createdAt);

        return $users;
    }

    public function seenSince(\DateTimeImmutable $since): array
    {
        $seen = array_values(array_filter(
            $this->byId,
            static fn(User $u): bool => $u->lastSeenAt !== null && $u->lastSeenAt >= $since,
        ));
        usort($seen, static fn(User $a, User $b): int => strcasecmp($a->pseudo, $b->pseudo));

        return $seen;
    }

    public function inactiveGuestIds(\DateTimeImmutable $before): array
    {
        $ids = [];
        foreach ($this->byId as $user) {
            if ($user->provider === Provider::Guest && ($user->lastSeenAt ?? $user->createdAt) < $before) {
                $ids[] = $user->id;
            }
        }

        return $ids;
    }

    public function touch(string $id, \DateTimeImmutable $now): void
    {
        $user = $this->byId[$id] ?? null;
        if ($user === null) {
            return;
        }
        $this->byId[$id] = new User(
            $user->id,
            $user->pseudo,
            $user->email,
            $user->provider,
            $user->role,
            $user->passwordHash,
            $user->suspended,
            $user->createdAt,
            $now,
        );
    }
}
