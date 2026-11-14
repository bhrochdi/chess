<?php

declare(strict_types=1);

namespace LeCercle\Infra\User;

use LeCercle\User\LoginAttempts;

/** Échecs de connexion dans la table `login_attempts` ; les lignes de plus de 24 h sont effacées à chaque écriture. */
final class PdoLoginAttempts implements LoginAttempts
{
    public const KEEP_MS = 86_400_000;

    public function __construct(private readonly \PDO $pdo) {}

    public function failuresSince(string $key, int $sinceMs): int
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM login_attempts WHERE attempt_key = ? AND attempted_at >= ?');
        $stmt->execute([$key, $sinceMs]);

        return (int) $stmt->fetchColumn();
    }

    public function record(string $key, int $atMs): void
    {
        $this->pdo->prepare('DELETE FROM login_attempts WHERE attempted_at < ?')->execute([$atMs - self::KEEP_MS]);
        $this->pdo->prepare('INSERT INTO login_attempts (attempt_key, attempted_at) VALUES (?, ?)')->execute([$key, $atMs]);
    }

    public function clear(string $key): void
    {
        $this->pdo->prepare('DELETE FROM login_attempts WHERE attempt_key = ?')->execute([$key]);
    }
}
