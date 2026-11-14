<?php

declare(strict_types=1);

namespace LeCercle\Tests\Support;

use LeCercle\Chat\Room;
use LeCercle\Chat\RoomRepository;

/** Double de test : salons en mémoire. */
final class InMemoryRoomRepository implements RoomRepository
{
    /** @var array<string, Room> */
    private array $byId = [];
    private int $counter = 0;

    public function nextId(): string
    {
        return \sprintf('r%012x', ++$this->counter);
    }

    public function all(): array
    {
        $rooms = array_values($this->byId);
        usort($rooms, static fn(Room $a, Room $b): int => [$a->position, $a->id] <=> [$b->position, $b->id]);

        return $rooms;
    }

    public function find(string $id): ?Room
    {
        return $this->byId[$id] ?? null;
    }

    public function nameTaken(string $name, ?string $exceptId = null): bool
    {
        foreach ($this->byId as $room) {
            if ($room->id !== $exceptId && mb_strtolower($room->name) === mb_strtolower($name)) {
                return true;
            }
        }

        return false;
    }

    public function add(Room $room): void
    {
        $this->byId[$room->id] = $room;
    }

    public function save(Room $room): void
    {
        $this->byId[$room->id] = $room;
    }

    public function delete(string $id): void
    {
        unset($this->byId[$id]);
    }

    public function maxPosition(): int
    {
        $max = -1;
        foreach ($this->byId as $room) {
            $max = max($max, $room->position);
        }

        return $max;
    }
}
