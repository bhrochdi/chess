<?php

declare(strict_types=1);

namespace LeCercle\Tests\Support;

use LeCercle\Shared\Clock;

/** Horloge de test figée, avancée à la main. */
final class FixedClock implements Clock
{
    public function __construct(private \DateTimeImmutable $now) {}

    public function now(): \DateTimeImmutable
    {
        return $this->now;
    }

    public function advanceMs(int $ms): void
    {
        $this->now = $this->now->modify(\sprintf('%+d milliseconds', $ms));
    }
}
