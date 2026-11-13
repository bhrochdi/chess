<?php

declare(strict_types=1);

namespace LeCercle\Tests\Support;

use LeCercle\Shared\TransactionRunner;

/** Double de test : exécute le travail directement, sans transaction. */
final class ImmediateTransactionRunner implements TransactionRunner
{
    public function run(callable $work): mixed
    {
        return $work();
    }
}
