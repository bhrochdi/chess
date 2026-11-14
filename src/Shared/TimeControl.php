<?php

declare(strict_types=1);

namespace LeCercle\Shared;

/** Cadence d'une partie ou d'un tournoi, en secondes, telle que le front l'envoie ({base, inc}). */
final readonly class TimeControl
{
    private const MESSAGE = 'Cadence invalide.';

    public function __construct(public int $baseSeconds, public int $incrementSeconds)
    {
        if ($baseSeconds < 1 || $baseSeconds > 10800 || $incrementSeconds < 0 || $incrementSeconds > 180) {
            throw new ValidationException(self::MESSAGE);
        }
    }

    /** @param array<mixed>|null $data */
    public static function fromArray(?array $data): ?self
    {
        if ($data === null) {
            return null;
        }
        $base = $data['base'] ?? null;
        $inc = $data['inc'] ?? null;
        if (!\is_int($base) || !\is_int($inc)) {
            throw new ValidationException(self::MESSAGE);
        }

        return new self($base, $inc);
    }

    /** @return array{base: int, inc: int} */
    public function toArray(): array
    {
        return ['base' => $this->baseSeconds, 'inc' => $this->incrementSeconds];
    }
}
