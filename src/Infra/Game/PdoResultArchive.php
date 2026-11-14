<?php

declare(strict_types=1);

namespace LeCercle\Infra\Game;

use LeCercle\Chess\Result;
use LeCercle\Game\ResultArchive;

/** Résultats archivés dans la table `archived_stats`, une ligne de compteurs par compte ; l'appelant ouvre la transaction. */
final class PdoResultArchive implements ResultArchive
{
    public function __construct(private readonly \PDO $pdo) {}

    public function keep(string $whiteId, string $blackId, Result $result): void
    {
        $draw = $result === Result::Draw ? 1 : 0;
        $this->add($whiteId, $result === Result::WhiteWins ? 1 : 0, $result === Result::BlackWins ? 1 : 0, $draw);
        $this->add($blackId, $result === Result::BlackWins ? 1 : 0, $result === Result::WhiteWins ? 1 : 0, $draw);
    }

    /** La lecture dans `users` écarte un compte supprimé, que la clé étrangère refuserait. */
    private function add(string $userId, int $wins, int $losses, int $draws): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO archived_stats (user_id, wins, losses, draws) SELECT id, ?, ?, ? FROM users WHERE id = ?'
            . ' ON DUPLICATE KEY UPDATE wins = archived_stats.wins + ?, losses = archived_stats.losses + ?,'
            . ' draws = archived_stats.draws + ?',
        );
        $stmt->execute([$wins, $losses, $draws, $userId, $wins, $losses, $draws]);
    }
}
