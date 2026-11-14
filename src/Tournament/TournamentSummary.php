<?php

declare(strict_types=1);

namespace LeCercle\Tournament;

use LeCercle\Shared\TimeControl;

/** Ligne de la liste des tournois (index léger, sans rondes ni inscrits). */
final readonly class TournamentSummary
{
    public function __construct(
        public string $id,
        public string $name,
        public TournamentFormat $format,
        public ?TimeControl $timeControl,
        public TournamentStatus $status,
        public int $playerCount,
        public ?\DateTimeImmutable $startedAt,
        public \DateTimeImmutable $createdAt,
    ) {}
}
