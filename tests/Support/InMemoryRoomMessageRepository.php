<?php

declare(strict_types=1);

namespace LeCercle\Tests\Support;

use LeCercle\Chat\ChatMessage;
use LeCercle\Chat\RoomMessageRepository;

/** Double de test : messages de salon en mémoire. */
final class InMemoryRoomMessageRepository implements RoomMessageRepository
{
    /** @var array<string, array<int, ChatMessage>> */
    private array $byRoom = [];
    private int $counter = 0;

    public function add(string $roomId, string $authorId, string $text, \DateTimeImmutable $at): ChatMessage
    {
        $message = new ChatMessage(++$this->counter, $authorId, $text, $at);
        $this->byRoom[$roomId][$message->id] = $message;

        return $message;
    }

    public function latest(string $roomId, int $limit): array
    {
        $all = array_values($this->byRoom[$roomId] ?? []);

        return $limit <= 0 ? [] : \array_slice($all, -$limit);
    }

    public function find(string $roomId, int $id): ?ChatMessage
    {
        return $this->byRoom[$roomId][$id] ?? null;
    }

    public function delete(string $roomId, int $id): void
    {
        unset($this->byRoom[$roomId][$id]);
    }

    public function clear(string $roomId): void
    {
        unset($this->byRoom[$roomId]);
    }
}
