<?php

declare(strict_types=1);

namespace LeCercle\Tests\Unit\Support;

use LeCercle\Tests\Support\InMemoryGameCommentRepository;
use PHPUnit\Framework\TestCase;

final class InMemoryGameCommentRepositoryTest extends TestCase
{
    public function testIdsAutoIncrementAcrossGames(): void
    {
        $repo = new InMemoryGameCommentRepository();
        $t = new \DateTimeImmutable('2026-11-12 10:00:00');

        self::assertSame(1, $repo->add('ga', 'u1', 'a', $t)->id);
        self::assertSame(2, $repo->add('gb', 'u1', 'b', $t)->id);
    }

    public function testLatestKeepsTheNewestOldestFirstPerGame(): void
    {
        $repo = new InMemoryGameCommentRepository();
        $t = new \DateTimeImmutable('2026-11-12 10:00:00');
        for ($i = 1; $i <= 5; ++$i) {
            $repo->add('ga', 'u1', "m$i", $t);
        }
        $repo->add('gb', 'u1', 'autre', $t);

        self::assertSame(['m3', 'm4', 'm5'], array_map(static fn($m): string => $m->text, $repo->latest('ga', 3)));
        self::assertSame(['autre'], array_map(static fn($m): string => $m->text, $repo->latest('gb', 3)));
        self::assertSame([], $repo->latest('gc', 3));
        self::assertSame([], $repo->latest('ga', 0));
    }
}
