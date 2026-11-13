<?php

declare(strict_types=1);

namespace LeCercle\User;

/** Statistiques de parties d'un compte. */
final readonly class Stats
{
    public function __construct(public int $wins = 0, public int $losses = 0, public int $draws = 0) {}

    /** @param array<mixed> $data clés w, l, d ; absente ou non entière → 0 */
    public static function fromArray(array $data): self
    {
        $int = static fn(mixed $v): int => is_int($v) ? $v : 0;

        return new self($int($data['w'] ?? null), $int($data['l'] ?? null), $int($data['d'] ?? null));
    }

    /** @return array{w: int, l: int, d: int} */
    public function toArray(): array
    {
        return ['w' => $this->wins, 'l' => $this->losses, 'd' => $this->draws];
    }
}
