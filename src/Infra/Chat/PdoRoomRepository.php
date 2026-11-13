<?php

declare(strict_types=1);

namespace LeCercle\Infra\Chat;

use LeCercle\Chat\Room;
use LeCercle\Chat\RoomRepository;
use LeCercle\Infra\Db\SqlTime;
use LeCercle\Shared\ConflictException;

/** Salons stockés dans la table `rooms`. */
final class PdoRoomRepository implements RoomRepository
{
    private const COLUMNS = 'id, name, description, position, archived, created_by, created_at';

    public function __construct(private readonly \PDO $pdo) {}

    public function nextId(): string
    {
        return 'r' . bin2hex(random_bytes(6));
    }

    public function all(): array
    {
        $stmt = $this->pdo->query('SELECT ' . self::COLUMNS . ' FROM rooms ORDER BY position, id');
        \assert($stmt instanceof \PDOStatement);

        return array_values(array_map(self::hydrate(...), $stmt->fetchAll()));
    }

    public function find(string $id): ?Room
    {
        $stmt = $this->pdo->prepare('SELECT ' . self::COLUMNS . ' FROM rooms WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();

        return \is_array($row) ? self::hydrate($row) : null;
    }

    public function nameTaken(string $name, ?string $exceptId = null): bool
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM rooms WHERE name = ? AND id <> ?');
        $stmt->execute([$name, $exceptId ?? '']);

        return (int) $stmt->fetchColumn() > 0;
    }

    public function add(Room $room): void
    {
        $this->write(
            'INSERT INTO rooms (id, name, description, position, archived, created_by, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)',
            [$room->id, $room->name, $room->description, $room->position, (int) $room->archived, $room->createdBy, SqlTime::format($room->createdAt)],
        );
    }

    public function save(Room $room): void
    {
        $this->write(
            'UPDATE rooms SET name = ?, description = ?, position = ?, archived = ? WHERE id = ?',
            [$room->name, $room->description, $room->position, (int) $room->archived, $room->id],
        );
    }

    public function delete(string $id): void
    {
        // La clé étrangère de room_messages (ON DELETE CASCADE) purge les messages.
        $this->pdo->prepare('DELETE FROM rooms WHERE id = ?')->execute([$id]);
    }

    public function maxPosition(): int
    {
        $stmt = $this->pdo->query('SELECT COALESCE(MAX(position), -1) FROM rooms');
        \assert($stmt instanceof \PDOStatement);

        return (int) $stmt->fetchColumn();
    }

    /** @param list<string|int|null> $params */
    private function write(string $sql, array $params): void
    {
        try {
            $this->pdo->prepare($sql)->execute($params);
        } catch (\PDOException $e) {
            // Clé unique sur le nom : même réponse que le contrôle du service (créations simultanées).
            if ($e->getCode() === '23000' && ($e->errorInfo[1] ?? null) === 1062) {
                throw new ConflictException('Ce nom de salon est déjà pris.', 0, $e);
            }
            throw $e;
        }
    }

    /** @param array<array-key, mixed> $row */
    private static function hydrate(array $row): Room
    {
        return new Room(
            (string) $row['id'],
            (string) $row['name'],
            (string) $row['description'],
            (int) $row['position'],
            (int) $row['archived'] === 1,
            $row['created_by'] === null ? null : (string) $row['created_by'],
            SqlTime::parse((string) $row['created_at']),
        );
    }
}
