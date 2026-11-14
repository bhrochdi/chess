<?php

declare(strict_types=1);

namespace LeCercle\Infra\Chat;

use LeCercle\Chat\CommentableGames;

/** Parties en ligne de la table `games`. */
final class PdoCommentableGames implements CommentableGames
{
    public function __construct(private readonly \PDO $pdo) {}

    public function exists(string $gameId): bool
    {
        $stmt = $this->pdo->prepare("SELECT 1 FROM games WHERE id = ? AND mode = 'online'");
        $stmt->execute([$gameId]);

        return $stmt->fetchColumn() !== false;
    }
}
