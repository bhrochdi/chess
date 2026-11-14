<?php

declare(strict_types=1);

namespace LeCercle\Infra\Chat;

use LeCercle\Chat\ChatMessage;
use LeCercle\Chat\RoomMessageRepository;
use LeCercle\Infra\Db\SqlTime;

/** Messages des salons stockés dans la table `room_messages`. */
final class PdoRoomMessageRepository implements RoomMessageRepository
{
    public function __construct(private readonly \PDO $pdo) {}

    public function add(string $roomId, string $authorId, string $text, \DateTimeImmutable $at): ChatMessage
    {
        $stmt = $this->pdo->prepare('INSERT INTO room_messages (room_id, author_id, body, created_at) VALUES (?, ?, ?, ?)');
        $stmt->execute([$roomId, $authorId, $text, SqlTime::format($at)]);

        return new ChatMessage((int) $this->pdo->lastInsertId(), $authorId, $text, $at);
    }

    public function latest(string $roomId, int $limit): array
    {
        $stmt = $this->pdo->prepare('SELECT id, author_id, body, created_at FROM room_messages WHERE room_id = ? ORDER BY id DESC LIMIT ?');
        $stmt->bindValue(1, $roomId);
        $stmt->bindValue(2, max(0, $limit), \PDO::PARAM_INT);
        $stmt->execute();

        return array_values(array_reverse(array_map(self::hydrate(...), $stmt->fetchAll())));
    }

    public function find(string $roomId, int $id): ?ChatMessage
    {
        $stmt = $this->pdo->prepare('SELECT id, author_id, body, created_at FROM room_messages WHERE room_id = ? AND id = ?');
        $stmt->execute([$roomId, $id]);
        $row = $stmt->fetch();

        return \is_array($row) ? self::hydrate($row) : null;
    }

    public function delete(string $roomId, int $id): void
    {
        $this->pdo->prepare('DELETE FROM room_messages WHERE room_id = ? AND id = ?')->execute([$roomId, $id]);
    }

    public function clear(string $roomId): void
    {
        $this->pdo->prepare('DELETE FROM room_messages WHERE room_id = ?')->execute([$roomId]);
    }

    /** @param array<array-key, mixed> $row */
    private static function hydrate(array $row): ChatMessage
    {
        return new ChatMessage((int) $row['id'], (string) $row['author_id'], (string) $row['body'], SqlTime::parse((string) $row['created_at']));
    }
}
