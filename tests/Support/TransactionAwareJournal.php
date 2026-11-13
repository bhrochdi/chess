<?php

declare(strict_types=1);

namespace LeCercle\Tests\Support;

use LeCercle\Shared\AdminJournal;

/** Double de test : note, pour chaque entrée, si elle a été écrite dans la transaction du runner observé. */
final class TransactionAwareJournal implements AdminJournal
{
    /** @var list<bool> */
    public array $insideTransaction = [];

    public function __construct(private readonly FlaggingTransactionRunner $runner) {}

    public function record(string $actorId, string $action): void
    {
        $this->insideTransaction[] = $this->runner->inside;
    }
}
