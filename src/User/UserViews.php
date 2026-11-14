<?php

declare(strict_types=1);

namespace LeCercle\User;

use LeCercle\Shared\Member;
use LeCercle\Shared\Millis;

/** Représentation JSON d'un compte. */
final class UserViews
{
    public function __construct(private readonly GameStats $stats) {}

    /**
     * L'e-mail n'est visible que par le titulaire du compte et par un administrateur.
     *
     * @return array{id: string, pseudo: string, email: ?string, provider: string, role: string, banned: bool, stats: array{w: int, l: int, d: int}, createdAt: int}
     */
    public function user(User $user, ?Member $viewer, ?Stats $stats = null): array
    {
        $stats ??= $this->stats->forUsers([$user->id])[$user->id] ?? new Stats();
        $seesEmail = $viewer !== null && ($viewer->id === $user->id || $viewer->isAdmin());

        return [
            'id' => $user->id,
            'pseudo' => $user->pseudo,
            'email' => $seesEmail ? $user->email : null,
            'provider' => $user->provider->value,
            'role' => $user->role->value,
            'banned' => $user->suspended,
            'stats' => $stats->toArray(),
            'createdAt' => Millis::of($user->createdAt),
        ];
    }

    /**
     * Plusieurs comptes, statistiques chargées en une seule lecture.
     *
     * @param list<User> $users
     *
     * @return list<array<string, mixed>>
     */
    public function users(array $users, ?Member $viewer): array
    {
        $stats = $this->stats->forUsers(array_map(static fn(User $u): string => $u->id, $users));

        return array_map(fn(User $u): array => $this->user($u, $viewer, $stats[$u->id] ?? new Stats()), $users);
    }
}
