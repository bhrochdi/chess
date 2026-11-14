<?php

declare(strict_types=1);

namespace LeCercle\Tournament;

/** Une ligne de classement ; les points sont comptés en demi-points pour rester entiers. */
final readonly class StandingRow
{
    public function __construct(
        public string $playerId,
        public int $halfPoints,
        public int $played,
        public int $wins,
    ) {}

    public function points(): float
    {
        return $this->halfPoints / 2;
    }
}
