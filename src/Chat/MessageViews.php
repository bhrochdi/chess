<?php

declare(strict_types=1);

namespace LeCercle\Chat;

use LeCercle\Shared\MemberRef;
use LeCercle\Shared\Members;
use LeCercle\Shared\Millis;

/** Représentations JSON des messages et des salons ; une seule lecture des comptes par réponse. */
final class MessageViews
{
    public function __construct(private readonly Members $members) {}

    /** @return array{id: int, author: array{id: string, pseudo: string}, text: string, createdAt: int} */
    public function one(ChatMessage $m): array
    {
        return $this->list([$m])[0];
    }

    /**
     * @param list<ChatMessage> $messages
     *
     * @return list<array{id: int, author: array{id: string, pseudo: string}, text: string, createdAt: int}>
     */
    public function list(array $messages): array
    {
        $authors = $this->members->findMany(array_map(static fn(ChatMessage $m): string => $m->authorId, $messages));

        return array_map(static fn(ChatMessage $m): array => [
            'id' => $m->id,
            'author' => MemberRef::resolve($m->authorId, $authors)->toArray(),
            'text' => $m->text,
            'createdAt' => Millis::of($m->createdAt),
        ], $messages);
    }

    /** @return array<string, mixed> */
    public function room(Room $room): array
    {
        return $this->rooms([$room])[0];
    }

    /**
     * @param list<Room> $rooms
     *
     * @return list<array<string, mixed>>
     */
    public function rooms(array $rooms): array
    {
        $ids = [];
        foreach ($rooms as $room) {
            if ($room->createdBy !== null) {
                $ids[] = $room->createdBy;
            }
        }
        $creators = $ids === [] ? [] : $this->members->findMany($ids);

        return array_map(static fn(Room $r): array => [
            'id' => $r->id,
            'name' => $r->name,
            'description' => $r->description,
            'position' => $r->position,
            'archived' => $r->archived,
            'isDefault' => $r->isDefault(),
            'createdBy' => $r->createdBy === null ? null : MemberRef::resolve($r->createdBy, $creators)->toArray(),
            'createdAt' => Millis::of($r->createdAt),
        ], $rooms);
    }
}
