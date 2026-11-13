<?php

declare(strict_types=1);

namespace LeCercle\Tests\Support;

use LeCercle\Shared\TransactionRunner;

/** Double de test : exécute le travail directement et indique si l'on est à l'intérieur de la transaction. */
final class FlaggingTransactionRunner implements TransactionRunner
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
