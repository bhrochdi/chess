<?php

declare(strict_types=1);

namespace LeCercle\Game;

/** Coup joué : UCI, SAN, temps restants des deux camps après le coup (null sans cadence) et instant du coup. */
final readonly class PlayedMove
{
    public function __construct(
        public string $uci,
        public string $san,
        public ?int $whiteMs,
        public ?int $blackMs,
        public \DateTimeImmutable $playedAt,
    ) {}
}
