<?php

declare(strict_types=1);

namespace LeCercle\Tests\Unit\Support;

use LeCercle\Tests\Support\InMemoryRoomMessageRepository;
use PHPUnit\Framework\TestCase;

final class InMemoryRoomMessageRepositoryTest extends TestCase
{
    public function testIdsAutoIncrementAndLatestKeepsTheNewestOldestFirst(): void
    {
        $repo = new InMemoryRoomMessageRepository();
        $t = new \DateTimeImmutable('2026-11-12 10:00:00');
        for ($i = 1; $i <= 5; ++$i) {
            self::assertSame($i, $repo->add('general', 'u1', "m$i", $t)->id);
        }

        self::assertSame(['m3', 'm4', 'm5'], array_map(static fn($m): string => $m->text, $repo->latest('general', 3)));
        self::assertSame([], $repo->latest('autre', 3));
    }

    public function testFindDeleteAndClear(): void
    {
        $repo = new InMemoryRoomMessageRepository();
        $t = new \DateTimeImmutable('2026-11-12 10:00:00');
        $repo->add('general', 'u1', 'a', $t);
        $repo->add('general', 'u1', 'b', $t);

        self::assertSame('a', $repo->find('general', 1)?->text);
        self::assertNull($repo->find('autre', 1));
        $repo->delete('general', 1);
        self::assertNull($repo->find('general', 1));
        $repo->clear('general');
        self::assertSame([], $repo->latest('general', 10));
    }
}
