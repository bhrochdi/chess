<?php

declare(strict_types=1);

namespace LeCercle\Tests\Support;

use LeCercle\User\GameStats;
use LeCercle\User\Stats;

/** Double de test : statistiques fixées par compte ; compte les appels. */
final class FixedGameStats implements GameStats
{
    public int $calls = 0;

    /** @param array<string, Stats> $byUser */
    public function __construct(private readonly array $byUser = []) {}

    public function forUsers(array $userIds): array
    {
        ++$this->calls;

        return array_intersect_key($this->byUser, array_flip($userIds));
    }
}
