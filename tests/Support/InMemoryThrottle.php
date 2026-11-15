<?php

declare(strict_types=1);

namespace LeCercle\Tests\Support;

use LeCercle\Shared\Throttle;

/** Double de test : même règle que le dépôt MySQL, clés gardées en clair. */
final class InMemoryThrottle implements Throttle
{
    /** @var array<string, list<int>> */
    private array $hits = [];

    public function allow(string $key, int $max, int $windowMs, int $nowMs): bool
    {
        $recent = array_filter($this->hits[$key] ?? [], static fn(int $at): bool => $at > $nowMs - $windowMs);
        if (\count($recent) >= $max) {
            return false;
        }
        $this->hits[$key][] = $nowMs;

        return true;
    }

    /** @return list<string> */
    public function keys(): array
    {
        return array_keys($this->hits);
    }
}
