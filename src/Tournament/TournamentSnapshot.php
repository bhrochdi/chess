<?php

declare(strict_types=1);

namespace LeCercle\Tournament;

use LeCercle\Shared\TimeControl;

/** État complet d'un tournoi tel que le dépôt le relit, pour Tournament::restore(). */
final readonly class TournamentSnapshot
{
    /**
     * @param list<string> $players ordre d'inscription
     * @param list<Round>  $rounds
     */
    public function __construct(
        public string $id,
        public string $name,
        public string $description,
        public TournamentFormat $format,
        public ?TimeControl $timeControl,
        public TournamentStatus $status,
        public bool $registrationOpen,
        public ?int $plannedRounds,
        public int $currentRound,
        public array $players,
        public array $rounds,
        public string $createdBy,
        public \DateTimeImmutable $createdAt,
        public ?\DateTimeImmutable $startedAt,
        public ?\DateTimeImmutable $finishedAt,
    ) {}
}
