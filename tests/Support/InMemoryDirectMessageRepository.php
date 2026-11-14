<?php

declare(strict_types=1);

namespace LeCercle\Tests\Support;

use LeCercle\Chat\ChatMessage;
use LeCercle\Chat\DirectMessageRepository;
use LeCercle\Chat\ThreadSummary;

/** Double de test : messages privés en mémoire. */
final class InMemoryDirectMessageRepository implements DirectMessageRepository
{
    /** @var list<array{message: ChatMessage, to: string}> */
    private array $all = [];
    /** @var array<string, int> marque de lecture par « utilisateur|interlocuteur » */
    private array $read = [];
    private int $counter = 0;

    public function add(string $senderId, string $recipientId, string $text, \DateTimeImmutable $at): ChatMessage
    {
        $message = new ChatMessage(++$this->counter, $senderId, $text, $at);
        $this->all[] = ['message' => $message, 'to' => $recipientId];

        return $message;
    }

    public function conversation(string $userId, string $peerId, int $limit): array
    {
        $messages = [];
        foreach ($this->all as $row) {
            $m = $row['message'];
            if (($m->authorId === $userId && $row['to'] === $peerId) || ($m->authorId === $peerId && $row['to'] === $userId)) {
                $messages[] = $m;
            }
        }

        return $limit <= 0 ? [] : \array_slice($messages, -$limit);
    }

    public function threads(string $userId, int $limit): array
    {
        return \array_slice($this->allThreads($userId), 0, max(0, $limit));
    }

    public function unreadTotal(string $userId): int
    {
        return array_sum(array_map(static fn(ThreadSummary $t): int => $t->unread, $this->allThreads($userId)));
    }

    public function markRead(string $userId, string $peerId, int $upToId): void
    {
        $key = $userId . '|' . $peerId;
        $this->read[$key] = max($this->read[$key] ?? 0, $upToId);
    }

    /** @return list<ThreadSummary> */
    private function allThreads(string $userId): array
    {
        $last = [];
        $unread = [];
        foreach ($this->all as $row) {
            $m = $row['message'];
            if ($m->authorId === $userId) {
                $peer = $row['to'];
            } elseif ($row['to'] === $userId) {
                $peer = $m->authorId;
                if ($m->id > ($this->read[$userId . '|' . $peer] ?? 0)) {
                    $unread[$peer] = ($unread[$peer] ?? 0) + 1;
                }
            } else {
                continue;
            }
            $last[$peer] = $m;
        }
        uasort($last, static fn(ChatMessage $a, ChatMessage $b): int => $b->id <=> $a->id);

        $threads = [];
        foreach ($last as $peer => $m) {
            $threads[] = new ThreadSummary((string) $peer, $m, $unread[$peer] ?? 0);
        }

        return $threads;
    }
}
