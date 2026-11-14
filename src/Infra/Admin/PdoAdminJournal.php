<?php

declare(strict_types=1);

namespace LeCercle\Infra\Admin;

use LeCercle\Infra\Db\SqlTime;
use LeCercle\Shared\AdminJournal;
use LeCercle\Shared\Clock;

/** Journal d'administration stocké dans la table `admin_log`. */
final class PdoAdminJournal implements AdminJournal
{
    private const MAX_ACTION_LENGTH = 500;

    public function __construct(private readonly \PDO $pdo, private readonly Clock $clock) {}

    public function record(string $actorId, string $action): void
    {
        if (mb_strlen($action) > self::MAX_ACTION_LENGTH) {
            throw new \InvalidArgumentException('Action du journal trop longue (500 caractères au plus).');
        }

        $stmt = $this->pdo->prepare('INSERT INTO admin_log (actor_id, action, created_at) VALUES (?, ?, ?)');
        $stmt->execute([$actorId, $action, SqlTime::format($this->clock->now())]);
    }
}
