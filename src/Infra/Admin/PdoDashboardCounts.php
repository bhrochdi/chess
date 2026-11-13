<?php

declare(strict_types=1);

namespace LeCercle\Infra\Admin;

use LeCercle\Admin\DashboardCounts;
use LeCercle\Infra\Db\SqlTime;

/** Chiffres du tableau de bord : comptes, présence, parties en ligne par statut, messages des salons. */
final class PdoDashboardCounts implements DashboardCounts
{
    public function __construct(private readonly \PDO $pdo) {}

    public function counts(\DateTimeImmutable $onlineSince): array
    {
        $games = ['open' => 0, 'playing' => 0, 'finished' => 0];
        $stmt = $this->pdo->prepare("SELECT status, COUNT(*) AS n FROM games WHERE mode = 'online' GROUP BY status");
        $stmt->execute();
        foreach ($stmt->fetchAll() as $row) {
            $games[(string) $row['status']] = (int) $row['n'];
        }

        return [
            'accounts' => $this->count('SELECT COUNT(*) FROM users', []),
            'online' => $this->count('SELECT COUNT(*) FROM users WHERE last_seen_at >= ?', [SqlTime::format($onlineSince)]),
            'open' => $games['open'],
            'playing' => $games['playing'],
            'finished' => $games['finished'],
            'roomMessages' => $this->count('SELECT COUNT(*) FROM room_messages', []),
        ];
    }

    /** @param list<string> $params */
    private function count(string $sql, array $params): int
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }
}
