<?php

declare(strict_types=1);

namespace LeCercle\Infra\User;

use LeCercle\User\GameStats;
use LeCercle\User\Stats;

/**
 * Statistiques : parties en ligne terminées de `games`, plus les résultats des parties purgées
 * cumulés dans `archived_stats` ; deux requêtes, quel que soit le nombre de comptes.
 */
final class PdoGameStats implements GameStats
{
    public function __construct(private readonly \PDO $pdo) {}

    public function forUsers(array $userIds): array
    {
        if ($userIds === []) {
            return [];
        }
        $marks = implode(', ', array_fill(0, \count($userIds), '?'));
        $stmt = $this->pdo->prepare(
            "SELECT white_id, black_id, result FROM games WHERE mode = 'online' AND status = 'finished'"
            . " AND (white_id IN ($marks) OR black_id IN ($marks))",
        );
        $stmt->execute([...$userIds, ...$userIds]);

        /** @var array<string, array{int, int, int}> $counts */
        $counts = [];
        foreach ($stmt->fetchAll() as $row) {
            foreach (['w' => $row['white_id'], 'b' => $row['black_id']] as $side => $id) {
                if (!\is_string($id) || !\in_array($id, $userIds, true)) {
                    continue;
                }
                $counts[$id] ??= [0, 0, 0];
                $won = ($row['result'] === '1-0' && $side === 'w') || ($row['result'] === '0-1' && $side === 'b');
                $index = $row['result'] === '1/2-1/2' ? 2 : ($won ? 0 : 1);
                ++$counts[$id][$index];
            }
        }

        $archived = $this->pdo->prepare("SELECT user_id, wins, losses, draws FROM archived_stats WHERE user_id IN ($marks)");
        $archived->execute($userIds);
        foreach ($archived->fetchAll() as $row) {
            $id = (string) $row['user_id'];
            $counts[$id] ??= [0, 0, 0];
            $counts[$id][0] += (int) $row['wins'];
            $counts[$id][1] += (int) $row['losses'];
            $counts[$id][2] += (int) $row['draws'];
        }

        return array_map(static fn(array $c): Stats => new Stats($c[0], $c[1], $c[2]), $counts);
    }
}
