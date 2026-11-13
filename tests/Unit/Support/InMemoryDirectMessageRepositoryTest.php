<?php

declare(strict_types=1);

namespace LeCercle\Tests\Unit\Support;

use LeCercle\Tests\Support\InMemoryDirectMessageRepository;
use PHPUnit\Framework\TestCase;

final class InMemoryDirectMessageRepositoryTest extends TestCase
{
    private \DateTimeImmutable $t;

    protected function setUp(): void
    {
        $this->t = new \DateTimeImmutable('2026-11-12 10:00:00');
    }

    public function testConversationIsBetweenTwoUsersAndKeepsTheNewest(): void
    {
        $repo = new InMemoryDirectMessageRepository();
        $repo->add('a', 'b', 'm1', $this->t);
        $repo->add('b', 'a', 'm2', $this->t);
        $repo->add('c', 'b', 'x', $this->t);
        $repo->add('b', 'c', 'y', $this->t);
        $repo->add('c', 'a', 'z', $this->t);
        $repo->add('a', 'b', 'm3', $this->t);

        $texts = static fn(array $ms): array => array_map(static fn($m): string => $m->text, $ms);
        self::assertSame(['m1', 'm2', 'm3'], $texts($repo->conversation('a', 'b', 10)));
        self::assertSame(['m2', 'm3'], $texts($repo->conversation('b', 'a', 2)));
    }

    public function testThreadsAreOrderedNewestFirstWithUnreadCounts(): void
    {
        $repo = new InMemoryDirectMessageRepository();
        $repo->add('b', 'a', 'b1', $this->t);
        $repo->add('b', 'a', 'b2', $this->t);
        $repo->add('c', 'a', 'c1', $this->t);
        $repo->add('a', 'd', 'mine', $this->t);

        $threads = $repo->threads('a', 10);
        self::assertSame(['d', 'c', 'b'], array_map(static fn($t): string => $t->peerId, $threads));
        self::assertSame([0, 1, 2], array_map(static fn($t): int => $t->unread, $threads));
        self::assertSame(3, $repo->unreadTotal('a'));
        self::assertCount(2, $repo->threads('a', 2));
    }

    public function testMarkReadNeverLowersTheMark(): void
    {
        $repo = new InMemoryDirectMessageRepository();
        $repo->add('b', 'a', 'b1', $this->t);
        $repo->add('b', 'a', 'b2', $this->t);
        $repo->add('b', 'a', 'b3', $this->t);

        $repo->markRead('a', 'b', 2);
        self::assertSame(1, $repo->unreadTotal('a'));
        $repo->markRead('a', 'b', 1);
        self::assertSame(1, $repo->unreadTotal('a'));
        $repo->markRead('a', 'b', 3);
        self::assertSame(0, $repo->unreadTotal('a'));
    }
}
