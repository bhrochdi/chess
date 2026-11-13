<?php

declare(strict_types=1);

namespace LeCercle\Infra\Chat;

use LeCercle\Chat\ChatMessage;
use LeCercle\Chat\DirectMessageRepository;
use LeCercle\Chat\ThreadSummary;
use LeCercle\Infra\Db\SqlTime;

/** Messages privés et marques de lecture (tables `direct_messages` et `dm_reads`). */
final class PdoDirectMessageRepository implements DirectMessageRepository
{
    public function __construct(private readonly \PDO $pdo) {}

    public function add(string $senderId, string $recipientId, string $text, \DateTimeImmutable $at): ChatMessage
    {
        $stmt = $this->pdo->prepare('INSERT INTO direct_messages (sender_id, recipient_id, body, created_at) VALUES (?, ?, ?, ?)');
        $stmt->execute([$senderId, $recipientId, $text, SqlTime::format($at)]);

        return new ChatMessage((int) $this->pdo->lastInsertId(), $senderId, $text, $at);
    }

    public function conversation(string $userId, string $peerId, int $limit): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, sender_id, body, created_at FROM direct_messages
             WHERE (sender_id = ? AND recipient_id = ?) OR (sender_id = ? AND recipient_id = ?)
             ORDER BY id DESC LIMIT ?',
        );
        $stmt->bindValue(1, $userId);
        $stmt->bindValue(2, $peerId);
        $stmt->bindValue(3, $peerId);
        $stmt->bindValue(4, $userId);
        $stmt->bindValue(5, max(0, $limit), \PDO::PARAM_INT);
        $stmt->execute();

        return array_values(array_reverse(array_map(self::hydrate(...), $stmt->fetchAll())));
    }

    public function threads(string $userId, int $limit): array
    {
        // Trois requêtes bornées, quel que soit le nombre de conversations.
        $stmt = $this->pdo->prepare(
            'SELECT peer_id, MAX(id) AS last_id FROM (
               SELECT recipient_id AS peer_id, id FROM direct_messages WHERE sender_id = ?
               UNION ALL
               SELECT sender_id AS peer_id, id FROM direct_messages WHERE recipient_id = ?
             ) t GROUP BY peer_id ORDER BY last_id DESC LIMIT ?',
        );
        $stmt->bindValue(1, $userId);
        $stmt->bindValue(2, $userId);
        $stmt->bindValue(3, max(0, $limit), \PDO::PARAM_INT);
        $stmt->execute();
        /** @var list<array{peer_id: string, last_id: int|string}> $heads */
        $heads = $stmt->fetchAll();
        if ($heads === []) {
            return [];
        }

        $ids = array_map(static fn(array $h): int => (int) $h['last_id'], $heads);
        $marks = implode(', ', array_fill(0, \count($ids), '?'));
        $stmt = $this->pdo->prepare("SELECT id, sender_id, body, created_at FROM direct_messages WHERE id IN ($marks)");
        $stmt->execute($ids);
        $lastById = [];
        foreach ($stmt->fetchAll() as $row) {
            $message = self::hydrate($row);
            $lastById[$message->id] = $message;
        }

        $unread = $this->unreadByPeer($userId);
        $threads = [];
        foreach ($heads as $head) {
            $peerId = (string) $head['peer_id'];
            $threads[] = new ThreadSummary($peerId, $lastById[(int) $head['last_id']], $unread[$peerId] ?? 0);
        }

        return $threads;
    }

    public function unreadTotal(string $userId): int
    {
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM direct_messages dm
             LEFT JOIN dm_reads r ON r.user_id = dm.recipient_id AND r.peer_id = dm.sender_id
             WHERE dm.recipient_id = ? AND dm.id > COALESCE(r.last_read_id, 0)',
        );
        $stmt->execute([$userId]);

        return (int) $stmt->fetchColumn();
    }

    public function markRead(string $userId, string $peerId, int $upToId): void
    {
        $this->pdo->prepare(
            'INSERT INTO dm_reads (user_id, peer_id, last_read_id) VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE last_read_id = GREATEST(last_read_id, VALUES(last_read_id))',
        )->execute([$userId, $peerId, $upToId]);
    }

    /** @return array<string, int> non-lus par interlocuteur */
    private function unreadByPeer(string $userId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT dm.sender_id, COUNT(*) AS unread FROM direct_messages dm
             LEFT JOIN dm_reads r ON r.user_id = dm.recipient_id AND r.peer_id = dm.sender_id
             WHERE dm.recipient_id = ? AND dm.id > COALESCE(r.last_read_id, 0) GROUP BY dm.sender_id',
        );
        $stmt->execute([$userId]);
        $unread = [];
        foreach ($stmt->fetchAll() as $row) {
            $unread[(string) $row['sender_id']] = (int) $row['unread'];
        }

        return $unread;
    }

    /** @param array<array-key, mixed> $row */
    private static function hydrate(array $row): ChatMessage
    {
        return new ChatMessage((int) $row['id'], (string) $row['sender_id'], (string) $row['body'], SqlTime::parse((string) $row['created_at']));
    }
}
