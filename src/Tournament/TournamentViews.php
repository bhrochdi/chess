<?php

declare(strict_types=1);

namespace LeCercle\Tournament;

use LeCercle\Shared\Member;
use LeCercle\Shared\MemberRef;
use LeCercle\Shared\Millis;

/**
 * Mise en forme JSON des tournois. Les membres sont chargés une seule fois par
 * le service (un seul Members::findMany par réponse) et passés ici.
 */
final class TournamentViews
{
    private const STATUS_ORDER = [
        TournamentStatus::Running->value => 0,
        TournamentStatus::Registration->value => 1,
        TournamentStatus::Finished->value => 2,
    ];

    /**
     * Identifiants à charger pour afficher le tournoi : inscrits, joueurs des
     * rencontres et créateur.
     *
     * @return list<string>
     */
    public static function memberIds(Tournament $t): array
    {
        $ids = $t->players();
        foreach ($t->rounds() as $round) {
            foreach ($round->pairings as $p) {
                $ids[] = $p->whiteId;
                $ids[] = $p->blackId;
            }
        }
        $ids[] = $t->createdBy();

        return array_values(array_unique(array_filter($ids, static fn(?string $id): bool => $id !== null)));
    }

    /**
     * @param array<string, Member> $members indexés par identifiant (Members::findMany)
     * @return array<string, mixed>
     */
    public function tournament(Tournament $t, ?Member $viewer, array $members): array
    {
        $ref = static fn(string $id): array => MemberRef::resolve($id, $members)->toArray();
        $pseudos = array_map(static fn(Member $m): string => $m->pseudo, $members);

        // Pendant le tournoi, un non-admin ne voit pas les rondes à venir (comme la vue actuelle).
        $hideFuture = $t->status() === TournamentStatus::Running && !($viewer?->isAdmin() ?? false);
        $rounds = [];
        foreach ($t->rounds() as $round) {
            if ($hideFuture && $round->number > $t->currentRound()) {
                continue;
            }
            $rounds[] = [
                'number' => $round->number,
                'pairings' => array_map(static fn(Pairing $p): array => [
                    'board' => $p->board,
                    'white' => $p->whiteId === null ? null : $ref($p->whiteId),
                    'black' => $p->blackId === null ? null : $ref($p->blackId),
                    'result' => $p->result?->value,
                    'gameId' => $p->gameId,
                ], $round->pairings),
            ];
        }

        return [
            'id' => $t->id(),
            'name' => $t->name(),
            'description' => $t->description(),
            'format' => $t->format()->value,
            'tc' => $t->timeControl()?->toArray(),
            'status' => $t->status()->value,
            'registrationOpen' => $t->registrationOpen(),
            'plannedRounds' => $t->plannedRounds(),
            'currentRound' => $t->currentRound(),
            'canAdvance' => $t->canAdvance(),
            'players' => array_map($ref, $t->players()),
            'rounds' => $rounds,
            'standings' => array_map(static fn(StandingRow $r): array => [
                'player' => $ref($r->playerId),
                'points' => $r->points(),
                'played' => $r->played,
                'wins' => $r->wins,
            ], $t->standings($pseudos)),
            'registered' => $viewer !== null && \in_array($viewer->id, $t->players(), true),
            'myPairing' => $viewer === null ? null : $this->pairingOf($t, $viewer->id),
            'createdBy' => $ref($t->createdBy()),
            'createdAt' => Millis::of($t->createdAt()),
            'startedAt' => $t->startedAt() === null ? null : Millis::of($t->startedAt()),
            'finishedAt' => $t->finishedAt() === null ? null : Millis::of($t->finishedAt()),
        ];
    }

    /**
     * @param list<TournamentSummary> $list
     * @return list<array<string, mixed>>
     */
    public function summaries(array $list): array
    {
        usort($list, static fn(TournamentSummary $a, TournamentSummary $b): int => self::STATUS_ORDER[$a->status->value] <=> self::STATUS_ORDER[$b->status->value]
            ?: $b->createdAt <=> $a->createdAt);

        return array_map(static fn(TournamentSummary $s): array => [
            'id' => $s->id,
            'name' => $s->name,
            'format' => $s->format->value,
            'tc' => $s->timeControl?->toArray(),
            'status' => $s->status->value,
            'playerCount' => $s->playerCount,
            'startedAt' => $s->startedAt === null ? null : Millis::of($s->startedAt),
        ], $list);
    }

    /** @return array{round: int, board: int, gameId: ?string}|null rencontre du visiteur dans la ronde en cours */
    private function pairingOf(Tournament $t, string $viewerId): ?array
    {
        if ($t->status() !== TournamentStatus::Running) {
            return null;
        }
        foreach ($t->rounds() as $round) {
            if ($round->number !== $t->currentRound()) {
                continue;
            }
            foreach ($round->pairings as $p) {
                if ($p->involves($viewerId)) {
                    return ['round' => $round->number, 'board' => $p->board, 'gameId' => $p->gameId];
                }
            }
        }

        return null;
    }
}
