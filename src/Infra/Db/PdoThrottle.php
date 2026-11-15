<?php

declare(strict_types=1);

namespace LeCercle\Infra\Db;

use LeCercle\Shared\Throttle;

/**
 * Événements comptés dans la table `throttle_hits`, sous l'empreinte SHA-256 de leur clé ; les lignes
 * de plus de 24 h sont effacées à chaque écriture. L'événement est inscrit avant d'être compté : des
 * requêtes simultanées ne peuvent pas dépasser la limite ensemble.
 */
final class PdoThrottle implements Throttle
{
    public const KEEP_MS = 86_400_000;

    public function __construct(private readonly \PDO $pdo) {}

    public function allow(string $key, int $max, int $windowMs, int $nowMs): bool
    {
        $hash = hash('sha256', $key);
        $this->pdo->prepare('DELETE FROM throttle_hits WHERE hit_at < ?')->execute([$nowMs - self::KEEP_MS]);
        $this->pdo->prepare('INSERT INTO throttle_hits (hit_key, hit_at) VALUES (?, ?)')->execute([$hash, $nowMs]);
        $id = (int) $this->pdo->lastInsertId();

        $count = $this->pdo->prepare('SELECT COUNT(*) FROM throttle_hits WHERE hit_key = ? AND hit_at > ?');
        $count->execute([$hash, $nowMs - $windowMs]);
        if ((int) $count->fetchColumn() <= $max) {
            return true;
        }
        // Refusé : l'événement n'est pas gardé, insister ne prolonge pas le blocage.
        $this->pdo->prepare('DELETE FROM throttle_hits WHERE id = ?')->execute([$id]);

        return false;
    }
}
