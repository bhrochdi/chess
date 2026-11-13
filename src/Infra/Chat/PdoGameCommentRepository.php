<?php

declare(strict_types=1);

namespace LeCercle\Infra\Chat;

use LeCercle\Chat\ChatMessage;
use LeCercle\Chat\GameCommentRepository;
use LeCercle\Infra\Db\SqlTime;

/** Commentaires de partie stockés dans la table `game_comments`. */
final class PdoGameCommentRepository implements GameCommentRepository
{
    public function __construct(private readonly \PDO $pdo) {}

    public function add(string $gameId, string $authorId, string $text, \DateTimeImmutable $at): ChatMessage
    {
        $stmt = $this->pdo->prepare('INSERT INTO game_comments (game_id, author_id, body, created_at) VALUES (?, ?, ?, ?)');
        $stmt->execute([$gameId, $authorId, $text, SqlTime::format($at)]);

        return new ChatMessage((int) $this->pdo->lastInsertId(), $authorId, $text, $at);
    }

    public function latest(string $gameId, int $limit): array
    {
        $stmt = $this->pdo->prepare('SELECT id, author_id, body, created_at FROM game_comments WHERE game_id = ? ORDER BY id DESC LIMIT ?');
        $stmt->bindValue(1, $gameId);
        $stmt->bindValue(2, max(0, $limit), \PDO::PARAM_INT);
        $stmt->execute();

        return array_values(array_reverse(array_map(self::hydrate(...), $stmt->fetchAll())));
    }

    /** @param array<array-key, mixed> $row */
    private static function hydrate(array $row): ChatMessage
    {
        return new ChatMessage((int) $row['id'], (string) $row['author_id'], (string) $row['body'], SqlTime::parse((string) $row['created_at']));
    }
}
