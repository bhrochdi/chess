<?php

declare(strict_types=1);

namespace LeCercle\Infra\Admin;

use LeCercle\Admin\JournalReader;
use LeCercle\Infra\Db\SqlTime;

/** Journal lu dans la table `admin_log`. */
final class PdoJournalReader implements JournalReader
{
    public function __construct(private readonly \PDO $pdo) {}

    public function latest(int $limit): array
    {
        $stmt = $this->pdo->prepare('SELECT actor_id, action, created_at FROM admin_log ORDER BY created_at DESC, id DESC LIMIT ?');
        $stmt->bindValue(1, $limit, \PDO::PARAM_INT);
        $stmt->execute();

        return array_values(array_map(static fn(array $row): array => [
            'actorId' => (string) $row['actor_id'],
            'action' => (string) $row['action'],
            'at' => SqlTime::parse((string) $row['created_at']),
        ], $stmt->fetchAll()));
    }
}
