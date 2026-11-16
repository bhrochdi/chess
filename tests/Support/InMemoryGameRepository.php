<?php

declare(strict_types=1);

namespace LeCercle\Tests\Support;

use LeCercle\Game\ChessRules;
use LeCercle\Game\Game;
use LeCercle\Game\GameMode;
use LeCercle\Game\GamePulse;
use LeCercle\Game\GameRepository;
use LeCercle\Game\GameSnapshot;
use LeCercle\Game\GameStatus;
use LeCercle\Game\GameSummary;
use LeCercle\Shared\NotFoundException;

/** Double de test : garde des instantanés et les rejoue à chaque lecture, comme le dépôt MySQL. */
final class InMemoryGameRepository implements GameRepository
{
    /** @var list<string> identifiants passés à getForUpdate(), dans l'ordre */
    public array $lockedIds = [];

    /** Lectures entières (get, getForUpdate) et lectures légères (pulse) : la garde du polling les compte. */
    public int $fullReads = 0;

    public int $pulseReads = 0;

    /** @var array<string, GameSnapshot> */
    private array $snapshots = [];

    private int $lastId = 0;

    public function __construct(private readonly ChessRules $rules) {}

    public function nextId(): string
    {
        return \sprintf('g%012x', ++$this->lastId);
    }

    public function add(Game $game): void
    {
        if (isset($this->snapshots[$game->id()])) {
            throw new \LogicException('Partie ' . $game->id() . ' déjà ajoutée.');
        }
        $this->snapshots[$game->id()] = $game->snapshot();
    }

    public function get(string $id): Game
    {
        ++$this->fullReads;

        return $this->restore($id);
    }

    public function getForUpdate(string $id): Game
    {
        ++$this->fullReads;
        $game = $this->restore($id);
        $this->lockedIds[] = $id;

        return $game;
    }

    private function restore(string $id): Game
    {
        $snapshot = $this->snapshots[$id] ?? throw new NotFoundException('Partie introuvable.');

        return Game::restore($snapshot, $this->rules);
    }

    public function pulse(string $id): GamePulse
    {
        ++$this->pulseReads;

        return GamePulse::of($this->restore($id));
    }

    public function save(Game $game): void
    {
        if (!isset($this->snapshots[$game->id()])) {
            throw new \LogicException('Partie ' . $game->id() . ' jamais ajoutée.');
        }
        $this->snapshots[$game->id()] = $game->snapshot();
    }

    public function delete(string $id): void
    {
        unset($this->snapshots[$id]);
    }

    public function finished(): array
    {
        $finished = array_values(array_filter($this->snapshots, static fn(GameSnapshot $s): bool => $s->status === GameStatus::Finished));
        usort($finished, static fn(GameSnapshot $a, GameSnapshot $b): int => $a->id <=> $b->id);

        return array_map(fn(GameSnapshot $s): GameSummary => GameSummary::of(Game::restore($s, $this->rules)), $finished);
    }

    public function all(int $limit): array
    {
        return $this->summaries(static fn(GameSnapshot $s): bool => true, $limit);
    }

    public function lobby(\DateTimeImmutable $finishedSince, int $limit): array
    {
        return $this->summaries(
            static fn(GameSnapshot $s): bool => $s->mode === GameMode::Online
                && ($s->status !== GameStatus::Finished || $s->updatedAt >= $finishedSince),
            $limit,
        );
    }

    public function finishedOf(string $userId, int $limit, int $offset): array
    {
        return $this->summaries(
            static fn(GameSnapshot $s): bool => $s->mode === GameMode::Online && $s->status === GameStatus::Finished
                && \in_array($userId, [$s->whiteId, $s->blackId], true),
            $limit,
            $offset,
        );
    }

    public function unfinishedOf(string $userId, int $limit): array
    {
        return $this->summaries(
            static fn(GameSnapshot $s): bool => $s->status !== GameStatus::Finished
                && \in_array($userId, [$s->ownerId, $s->whiteId, $s->blackId], true),
            $limit,
        );
    }

    /**
     * Même ordre que le SQL : updated_at décroissant, puis identifiant croissant.
     *
     * @param \Closure(GameSnapshot): bool $keep
     *
     * @return list<GameSummary>
     */
    private function summaries(\Closure $keep, int $limit, int $offset = 0): array
    {
        $kept = array_values(array_filter($this->snapshots, $keep));
        usort($kept, static fn(GameSnapshot $a, GameSnapshot $b): int => [$b->updatedAt, $a->id] <=> [$a->updatedAt, $b->id]);

        return array_map(
            fn(GameSnapshot $s): GameSummary => GameSummary::of(Game::restore($s, $this->rules)),
            \array_slice($kept, max(0, $offset), max(0, $limit)),
        );
    }
}
