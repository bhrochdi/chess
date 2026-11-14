<?php

declare(strict_types=1);

namespace LeCercle\User;

use LeCercle\Shared\Access;
use LeCercle\Shared\Clock;

/** Présence des joueurs : un battement de cœur met à jour `last_seen_at`, la liste en ligne est une seule requête. */
final class PresenceService
{
    public const ONLINE_WINDOW_SECONDS = 70;

    public function __construct(
        private readonly UserRepository $users,
        private readonly Access $access,
        private readonly Clock $clock,
    ) {}

    /** @throws \LeCercle\Shared\ForbiddenException non connecté ou suspendu */
    public function heartbeat(): void
    {
        $member = $this->access->member();
        $this->users->touch($member->id, $this->clock->now());
    }

    /**
     * Les comptes vus dans la fenêtre, triés par pseudo (ouvert à tous).
     *
     * @return list<array{id: string, pseudo: string}>
     */
    public function online(): array
    {
        $since = $this->clock->now()->modify(\sprintf('-%d seconds', self::ONLINE_WINDOW_SECONDS));

        return array_map(
            static fn(User $user): array => ['id' => $user->id, 'pseudo' => $user->pseudo],
            $this->users->seenSince($since),
        );
    }
}
