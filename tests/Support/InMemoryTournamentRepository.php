<?php

declare(strict_types=1);

namespace LeCercle\Tests\Support;

use LeCercle\Shared\NotFoundException;
use LeCercle\Tournament\Tournament;
use LeCercle\Tournament\TournamentRepository;
use LeCercle\Tournament\TournamentSnapshot;
use LeCercle\Tournament\TournamentStatus;
use LeCercle\Tournament\TournamentSummary;

/**
 * Double de test : garde des instantanés et rend des copies reconstruites par
 * Tournament::restore(), comme le ferait un dépôt SQL.
 */
final class InMemoryTournamentRepository implements TournamentRepository
{
    /** @var array<string, TournamentSnapshot> */
    private array $byId = [];

    private int $sequence = 0;

    public function nextId(): string
    {
        return \sprintf('t%012x', ++$this->sequence);
    }

    public function add(Tournament $t): void
    {
        $this->byId[$t->id()] = self::snapshot($t);
    }

    public function get(string $id): Tournament
    {
        $snapshot = $this->byId[$id] ?? throw new NotFoundException('Tournoi introuvable.');

        return Tournament::restore($snapshot);
    }

    public function getForUpdate(string $id): Tournament
    {
        return $this->get($id);
    }

    public function findByGameId(string $gameId): ?Tournament
    {
        foreach ($this->byId as $id => $snapshot) {
            foreach ($snapshot->rounds as $round) {
                foreach ($round->pairings as $p) {
                    if ($p->gameId === $gameId) {
                        return $this->get((string) $id);
                    }
                }
            }
        }

        return null;
    }

    public function save(Tournament $t): void
    {
        $this->byId[$t->id()] = self::snapshot($t);
    }

    public function delete(string $id): void
    {
        unset($this->byId[$id]);
    }

    public function summaries(): array
    {
        return array_values(array_map(
            static fn(TournamentSnapshot $s): TournamentSummary => new TournamentSummary(
                $s->id,
                $s->name,
                $s->format,
                $s->timeControl,
                $s->status,
                \count($s->players),
                $s->startedAt,
                $s->createdAt,
            ),
            $this->byId,
        ));
    }

    public function nameTaken(string $name, ?string $exceptId = null): bool
    {
        foreach ($this->byId as $id => $s) {
            if ($id !== $exceptId && mb_strtolower($s->name) === mb_strtolower($name)) {
                return true;
            }
        }

        return false;
    }

    public function runningExists(?string $exceptId = null): bool
    {
        foreach ($this->byId as $id => $s) {
            if ($id !== $exceptId && $s->status === TournamentStatus::Running) {
                return true;
            }
        }

        return false;
    }

    private static function snapshot(Tournament $t): TournamentSnapshot
    {
        return new TournamentSnapshot(
            $t->id(),
            $t->name(),
            $t->description(),
            $t->format(),
            $t->timeControl(),
            $t->status(),
            $t->registrationOpen(),
            $t->plannedRounds(),
            $t->currentRound(),
            $t->players(),
            $t->rounds(),
            $t->createdBy(),
            $t->createdAt(),
            $t->startedAt(),
            $t->finishedAt(),
        );
    }
}
