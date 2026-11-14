<?php

declare(strict_types=1);

namespace LeCercle\Game;

use LeCercle\Chess\Color;
use LeCercle\Shared\Member;
use LeCercle\Shared\MemberRef;
use LeCercle\Shared\Members;
use LeCercle\Shared\Millis;

/** Modèle de lecture : l'état JSON d'une partie et les résumés des listes, tels que le contrat de l'API les décrit. */
final class GameViews
{
    public function __construct(private readonly Members $members, private readonly ChessRules $rules) {}

    /**
     * Les temps restants sont ceux de $now, que l'appelant fournit (le service relit l'horloge après ses écritures).
     *
     * @return array<string, mixed> le « game » du contrat de l'API
     */
    public function game(Game $game, ?Member $viewer, \DateTimeImmutable $now): array
    {
        [$white, $black] = $this->seatRefs($game->mode(), $game->ownerId(), $game->whiteId(), $game->blackId(), $this->resolve([
            $game->whiteId(),
            $game->blackId(),
        ]));
        $position = $game->position();
        $colors = $game->colorsOf($viewer?->id);
        $moves = $game->moves();
        $last = $moves === [] ? null : $moves[\count($moves) - 1]->uci;
        $mayMove = $game->status() === GameStatus::Playing && \in_array($position->sideToMove(), $colors, true);

        return [
            'id' => $game->id(),
            'mode' => $game->mode()->value,
            'status' => $game->status()->value,
            'level' => $game->level()?->value,
            'white' => $white,
            'black' => $black,
            'tc' => $game->timeControl()?->toArray(),
            'fen' => $position->toFen(),
            'turn' => $position->sideToMove()->value,
            'moves' => array_map(static fn(PlayedMove $m): string => $m->san, $moves),
            'lastMove' => $last === null ? null : ['from' => substr($last, 0, 2), 'to' => substr($last, 2, 2)],
            'legalMoves' => $mayMove ? $this->rules->legalUcis($position) : [],
            'inCheck' => $this->rules->inCheck($position),
            'captured' => $game->captured(),
            'clock' => $this->clock($game->clock(), $now),
            'drawOffer' => $game->drawOffer()?->value,
            'result' => $game->result()?->value,
            'reason' => $game->reason()?->value,
            'yourColor' => match (\count($colors)) {
                0 => null,
                2 => 'both',
                default => $colors[0]->value,
            },
            'createdAt' => Millis::of($game->createdAt()),
            'updatedAt' => Millis::of($game->updatedAt()),
        ];
    }

    /**
     * @param list<GameSummary> $games
     *
     * @return list<array<string, mixed>> un seul Members::findMany() pour toute la liste
     */
    public function summaries(array $games): array
    {
        $ids = [];
        foreach ($games as $g) {
            $ids[] = $g->whiteId;
            $ids[] = $g->blackId;
        }
        $people = $this->resolve($ids);

        $list = [];
        foreach ($games as $g) {
            [$white, $black] = $this->seatRefs($g->mode, $g->ownerId, $g->whiteId, $g->blackId, $people);
            $list[] = [
                'id' => $g->id,
                'mode' => $g->mode->value,
                'status' => $g->status->value,
                'white' => $white,
                'black' => $black,
                'level' => $g->level?->value,
                'tc' => $g->timeControl?->toArray(),
                'result' => $g->result?->value,
                'reason' => $g->reason?->value,
                'updatedAt' => Millis::of($g->updatedAt),
            ];
        }

        return $list;
    }

    /** @return array{0: string, 1: string} noms des blancs et des noirs pour les en-têtes PGN */
    public function playerNames(Game $game): array
    {
        return match ($game->mode()) {
            GameMode::Local => ['Joueur Blancs', 'Joueur Noirs'],
            GameMode::Computer => ['Joueur Blancs', 'Ordinateur'],
            GameMode::Online => $this->seatNames($game->whiteId(), $game->blackId()),
        };
    }

    /** @return array{0: string, 1: string} un seul findMany() pour les deux sièges */
    private function seatNames(?string $whiteId, ?string $blackId): array
    {
        $people = $this->resolve([$whiteId, $blackId]);

        return [
            $whiteId === null ? '?' : MemberRef::resolve($whiteId, $people)->pseudo,
            $blackId === null ? '?' : MemberRef::resolve($blackId, $people)->pseudo,
        ];
    }

    /**
     * Un seul appel à Members::findMany() par réponse.
     *
     * @param list<string|null> $ids
     *
     * @return array<string, Member>
     */
    private function resolve(array $ids): array
    {
        return $this->members->findMany(array_values(array_unique(array_filter($ids, static fn(?string $id): bool => $id !== null))));
    }

    /**
     * Local : le propriétaire tient les deux camps ; contre l'ordinateur : noirs absents ; en ligne : les sièges.
     *
     * @param array<string, Member> $people
     *
     * @return array{0: ?array{id: string, pseudo: string}, 1: ?array{id: string, pseudo: string}}
     */
    private function seatRefs(GameMode $mode, string $ownerId, ?string $whiteId, ?string $blackId, array $people): array
    {
        [$white, $black] = match ($mode) {
            GameMode::Local => [$ownerId, $ownerId],
            GameMode::Computer => [$ownerId, null],
            GameMode::Online => [$whiteId, $blackId],
        };

        return [
            $white === null ? null : MemberRef::resolve($white, $people)->toArray(),
            $black === null ? null : MemberRef::resolve($black, $people)->toArray(),
        ];
    }

    /** @return array{whiteMs: int, blackMs: int, running: ?string, serverNow: int}|null */
    private function clock(?GameClock $clock, \DateTimeImmutable $now): ?array
    {
        if ($clock === null) {
            return null;
        }

        return [
            'whiteMs' => $clock->remainingMs(Color::White, $now),
            'blackMs' => $clock->remainingMs(Color::Black, $now),
            'running' => $clock->running?->value,
            // Exception documentée à « les clés d'instants finissent par At » : c'est l'instant de référence
            // du serveur, d'où le front compte à rebours à partir de whiteMs/blackMs.
            'serverNow' => Millis::of($now),
        ];
    }
}
