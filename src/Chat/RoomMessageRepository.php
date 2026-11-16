<?php

declare(strict_types=1);

namespace LeCercle\Chat;

/** Stockage des messages des salons. */
interface RoomMessageRepository
{
    public function add(string $roomId, string $authorId, string $text, \DateTimeImmutable $at): ChatMessage;

    /** @return list<ChatMessage> les $limit plus récents, du plus ancien au plus récent */
    public function latest(string $roomId, int $limit): array;

    /** Identifiant du message le plus récent du salon, 0 s'il est vide. */
    public function lastId(string $roomId): int;

    public function find(string $roomId, int $id): ?ChatMessage;

    public function delete(string $roomId, int $id): void;

    public function clear(string $roomId): void;
}
