<?php

declare(strict_types=1);

namespace LeCercle\Tournament;

/** Une ronde : ses rencontres, numérotées par échiquier. */
final readonly class Round
{
    /** @param list<Pairing> $pairings */
    public function __construct(public int $number, public array $pairings) {}

    public function isComplete(): bool
    {
        foreach ($this->pairings as $p) {
            if ($p->result === null) {
                return false;
            }
        }

        return true;
    }

    public function pairing(int $board): ?Pairing
    {
        foreach ($this->pairings as $p) {
            if ($p->board === $board) {
                return $p;
            }
        }

        return null;
    }

    /** Remplace la rencontre de même échiquier. */
    public function withPairing(Pairing $p): self
    {
        return new self($this->number, array_map(
            static fn(Pairing $q): Pairing => $q->board === $p->board ? $p : $q,
            $this->pairings,
        ));
    }
}
