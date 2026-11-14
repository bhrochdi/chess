<?php

declare(strict_types=1);

namespace LeCercle\Tests\Support;

use LeCercle\User\LoginAttempts;

final class InMemoryLoginAttempts implements LoginAttempts
{
    /** @var array<string, list<int>> */
    private array $failures = [];

    public function failuresSince(string $key, int $sinceMs): int
    {
        return \count(array_filter($this->failures[$key] ?? [], static fn(int $at): bool => $at >= $sinceMs));
    }

    public function record(string $key, int $atMs): void
    {
        $this->failures[$key][] = $atMs;
    }

    public function clear(string $key): void
    {
        unset($this->failures[$key]);
    }

    /** @return list<string> */
    public function keys(): array
    {
        return array_keys($this->failures);
    }
}
