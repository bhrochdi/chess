<?php

declare(strict_types=1);

namespace LeCercle\Infra;

use LeCercle\Shared\Clock;

/** Horloge réelle, en UTC. */
final class SystemClock implements Clock
{
    public function now(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }
}
