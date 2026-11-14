<?php

declare(strict_types=1);

namespace LeCercle\Game;

use LeCercle\Chess\Color;
use LeCercle\Shared\Millis;
use LeCercle\Shared\TimeControl;

/** Pendule à partir de l'heure du serveur : temps restants mémorisés au début du trait en cours. */
final readonly class GameClock
{
    public function __construct(
        public int $whiteMs,
        public int $blackMs,
        public int $incrementMs,
        public ?Color $running,
        public ?\DateTimeImmutable $since,
    ) {}

    public static function start(TimeControl $tc): self
    {
        $ms = $tc->baseSeconds * 1000;

        return new self($ms, $ms, $tc->incrementSeconds * 1000, null, null);
    }

    /** Temps stocké moins le temps écoulé si $c a le trait ; jamais négatif, un $now antérieur au début du trait n'ajoute rien. */
    public function remainingMs(Color $c, \DateTimeImmutable $now): int
    {
        $stored = $this->stored($c);
        if ($this->running !== $c || $this->since === null) {
            return $stored;
        }
        $elapsed = max(0, Millis::of($now) - Millis::of($this->since));

        return max(0, $stored - $elapsed);
    }

    public function runFor(Color $c, \DateTimeImmutable $now): self
    {
        return new self($this->whiteMs, $this->blackMs, $this->incrementMs, $c, $now);
    }

    /** Port de doMove (js/core/game.js) : pendule arrêtée = ni retrait ni incrément ; l'adversaire part de $now. */
    public function afterMove(Color $mover, \DateTimeImmutable $now): self
    {
        $times = [Color::White->value => $this->whiteMs, Color::Black->value => $this->blackMs];
        if ($this->running === $mover) {
            $times[$mover->value] = $this->remainingMs($mover, $now) + $this->incrementMs;
        }

        return new self(
            $times[Color::White->value],
            $times[Color::Black->value],
            $this->incrementMs,
            $mover->opponent(),
            $now,
        );
    }

    public function stopped(\DateTimeImmutable $now): self
    {
        $white = $this->running === Color::White ? $this->remainingMs(Color::White, $now) : $this->whiteMs;
        $black = $this->running === Color::Black ? $this->remainingMs(Color::Black, $now) : $this->blackMs;

        return new self($white, $black, $this->incrementMs, null, null);
    }

    public function flagged(\DateTimeImmutable $now): ?Color
    {
        if ($this->running !== null && $this->remainingMs($this->running, $now) <= 0) {
            return $this->running;
        }

        return null;
    }

    /** Pour l'annulation de coup : remet des temps et un trait donnés, l'incrément est conservé. */
    public function withTimes(int $whiteMs, int $blackMs, ?Color $running, ?\DateTimeImmutable $since): self
    {
        return new self($whiteMs, $blackMs, $this->incrementMs, $running, $since);
    }

    private function stored(Color $c): int
    {
        return $c === Color::White ? $this->whiteMs : $this->blackMs;
    }
}
