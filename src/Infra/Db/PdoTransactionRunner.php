<?php

declare(strict_types=1);

namespace LeCercle\Infra\Db;

use LeCercle\Shared\TransactionRunner;
use PDO;
use Throwable;

/**
 * Transaction PDO : annulée et l'exception relancée en cas d'échec. Dans une
 * transaction déjà ouverte, le travail s'y joint (pas de point de sauvegarde).
 */
final class PdoTransactionRunner implements TransactionRunner
{
    public function __construct(private readonly PDO $pdo) {}

    public function run(callable $work): mixed
    {
        if ($this->isOpen()) {
            return $work();
        }

        $this->pdo->beginTransaction();
        try {
            $result = $work();
            $this->pdo->commit();
        } catch (Throwable $e) {
            if ($this->isOpen()) {
                $this->pdo->rollBack();
            }

            throw $e;
        }

        return $result;
    }

    /** @phpstan-impure */
    private function isOpen(): bool
    {
        return $this->pdo->inTransaction();
    }
}
