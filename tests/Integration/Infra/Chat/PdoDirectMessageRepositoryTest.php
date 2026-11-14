<?php

declare(strict_types=1);

namespace LeCercle\Tests\Integration\Infra\Chat;

use LeCercle\Chat\ChatMessage;
use LeCercle\Chat\ThreadSummary;
use LeCercle\Infra\Chat\PdoDirectMessageRepository;
use LeCercle\Tests\Integration\DatabaseTestCase;

final class PdoDirectMessageRepositoryTest extends DatabaseTestCase
{
    private function repo(): PdoDirectMessageRepository
    {
        return new PdoDirectMessageRepository(self::pdo());
    }

    private function at(): \DateTimeImmutable
    {
        // Même horodatage partout : l'ordre ne peut venir que de l'identifiant.
        return new \DateTimeImmutable('2026-11-12 10:00:00.000', new \DateTimeZone('UTC'));
    }

    /**
     * @param list<ChatMessage> $messages
     * @return list<string>
     */
    private static function texts(array $messages): array
    {
        return array_map(static fn(ChatMessage $m): string => $m->text, $messages);
    }

    /**
     * @param list<ThreadSummary> $threads
     * @return array<string, int>
     */
    private static function unread(array $threads): array
    {
        $map = [];
        foreach ($threads as $t) {
            $map[$t->peerId] = $t->unread;
        }

        return $map;
    }

    public function testConversationHoldsBothDirectionsOnly(): void
    {
        $repo = $this->repo();
        $repo->add('alice', 'bob', 'a->b', $this->at());
        $repo->add('bob', 'alice', 'b->a', $this->at());
        $repo->add('alice', 'carol', 'a->c', $this->at());
        $repo->add('carol', 'bob', 'c->b', $this->at());

        self::assertSame(['a->b', 'b->a'], self::texts($repo->conversation('alice', 'bob', 100)));
        self::assertSame(['a->b', 'b->a'], self::texts($repo->conversation('bob', 'alice', 100)));
    }

    public function testConversationLimitKeepsTheLatestOldestFirst(): void
    {
        $repo = $this->repo();
        foreach (['un', 'deux', 'trois'] as $i => $text) {
            $repo->add($i % 2 === 0 ? 'alice' : 'bob', $i % 2 === 0 ? 'bob' : 'alice', $text, $this->at());
        }

        self::assertSame(['deux', 'trois'], self::texts($repo->conversation('alice', 'bob', 2)));
    }

    public function testAddReturnsTheStoredMessage(): void
    {
        $m = $this->repo()->add('alice', 'bob', 'salut', new \DateTimeImmutable('2026-11-12 10:00:00.321', new \DateTimeZone('UTC')));

        self::assertGreaterThan(0, $m->id);
        self::assertSame('alice', $m->authorId);
        self::assertSame('2026-11-12 10:00:00.321', $m->createdAt->format('Y-m-d H:i:s.v'));
    }

    public function testThreadsAreScopedToTheUser(): void
    {
        $repo = $this->repo();
        $repo->add('bob', 'alice', 'b->a', $this->at());
        $repo->add('carol', 'bob', 'c->b', $this->at());
        $repo->add('bob', 'carol', 'b->c', $this->at());

        $threads = $repo->threads('alice', 12);

        self::assertSame(['bob'], array_map(static fn(ThreadSummary $t): string => $t->peerId, $threads));
        self::assertSame('b->a', $threads[0]->last->text);
    }

    public function testThreadsAreOrderedByNewestMessageIdAndShowLastMessage(): void
    {
        $repo = $this->repo();
        $repo->add('alice', 'bob', 'ancien bob', $this->at());
        $repo->add('carol', 'alice', 'carol', $this->at());
        $repo->add('bob', 'alice', 'récent bob', $this->at());
        $repo->add('alice', 'dave', 'dave', $this->at());

        $threads = $repo->threads('alice', 12);

        self::assertSame(['dave', 'bob', 'carol'], array_map(static fn(ThreadSummary $t): string => $t->peerId, $threads));
        self::assertSame('récent bob', $threads[1]->last->text);
    }

    public function testThreadsLimitKeepsTheMostRecent(): void
    {
        $repo = $this->repo();
        foreach (['b', 'c', 'd'] as $peer) {
            $repo->add($peer, 'alice', $peer, $this->at());
        }

        self::assertSame(['d', 'c'], array_map(static fn(ThreadSummary $t): string => $t->peerId, $repo->threads('alice', 2)));
    }

    public function testUnreadCountsAndMarkRead(): void
    {
        $repo = $this->repo();
        $repo->add('bob', 'alice', 'b1', $this->at());
        $second = $repo->add('bob', 'alice', 'b2', $this->at());
        $repo->add('carol', 'alice', 'c1', $this->at());
        $repo->add('alice', 'bob', 'mon message', $this->at());

        self::assertSame(['bob' => 2, 'carol' => 1], self::unread($repo->threads('alice', 12)));
        self::assertSame(3, $repo->unreadTotal('alice'));
        self::assertSame(1, $repo->unreadTotal('bob'), 'bob n\'a lu que ses propres messages envoyés : un non-lu');

        $repo->markRead('alice', 'bob', $second->id);

        self::assertSame(['bob' => 0, 'carol' => 1], self::unread($repo->threads('alice', 12)));
        self::assertSame(1, $repo->unreadTotal('alice'));
    }

    public function testMarkReadNeverMovesBackwards(): void
    {
        $repo = $this->repo();
        $repo->add('bob', 'alice', 'b1', $this->at());
        $second = $repo->add('bob', 'alice', 'b2', $this->at());
        $third = $repo->add('bob', 'alice', 'b3', $this->at());

        $repo->markRead('alice', 'bob', $third->id);
        $repo->markRead('alice', 'bob', $second->id);

        self::assertSame(0, $repo->unreadTotal('alice'));
        $row = self::pdo()->query("SELECT last_read_id FROM dm_reads WHERE user_id = 'alice' AND peer_id = 'bob'");
        self::assertNotFalse($row);
        self::assertSame($third->id, (int) $row->fetchColumn());
    }

    public function testMessagesSentByTheUserAreNeverUnread(): void
    {
        $repo = $this->repo();
        $repo->add('alice', 'bob', 'sortant', $this->at());

        self::assertSame(0, $repo->unreadTotal('alice'));
        self::assertSame(['bob' => 0], self::unread($repo->threads('alice', 12)));
    }
}
