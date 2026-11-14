<?php

declare(strict_types=1);

namespace LeCercle\Admin;

use LeCercle\Shared\Access;
use LeCercle\Shared\Clock;
use LeCercle\Shared\MemberRef;
use LeCercle\Shared\Members;
use LeCercle\Shared\Millis;

/** Journal et tableau de bord de l'administration, en lecture seule. */
final class AdminService
{
    public const JOURNAL_LIMIT = 200;
    /** Même fenêtre que la liste des joueurs en ligne. */
    public const ONLINE_WINDOW_SECONDS = 70;

    public function __construct(
        private readonly Access $access,
        private readonly Members $members,
        private readonly Clock $clock,
        private readonly JournalReader $journal,
        private readonly DashboardCounts $counts,
    ) {}

    /** @return list<array{actor: array{id: string, pseudo: string}, action: string, at: int}> */
    public function journal(): array
    {
        $this->access->admin();
        $entries = $this->journal->latest(self::JOURNAL_LIMIT);
        $members = $this->members->findMany(array_column($entries, 'actorId'));

        return array_map(static fn(array $e): array => [
            'actor' => MemberRef::resolve($e['actorId'], $members)->toArray(),
            'action' => $e['action'],
            'at' => Millis::of($e['at']),
        ], $entries);
    }

    /** @return array{accounts: int, online: int, open: int, playing: int, finished: int, roomMessages: int} */
    public function dashboard(): array
    {
        $this->access->admin();

        return $this->counts->counts($this->clock->now()->modify(\sprintf('-%d seconds', self::ONLINE_WINDOW_SECONDS)));
    }
}
