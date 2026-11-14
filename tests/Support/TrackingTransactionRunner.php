<?php

declare(strict_types=1);

namespace LeCercle\Tests\Support;

use LeCercle\Shared\TransactionRunner;

/** Double de test : exécute le travail directement et indique s'il est en cours. */
final class TrackingTransactionRunner implements TransactionRunner
{
    public bool $inside = false;

    public function run(callable $work): mixed
    {
        $this->inside = true;
        try {
            return $work();
        } finally {
            $this->inside = false;
        }
    }
}
