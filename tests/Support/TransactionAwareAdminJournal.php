<?php

declare(strict_types=1);

namespace LeCercle\Tests\Support;

use LeCercle\Shared\AdminJournal;

/** Journal de test : retient, pour chaque entrée, si elle a été écrite pendant la transaction. */
final class TransactionAwareAdminJournal implements AdminJournal
{
    /** @var list<array{action: string, inside: bool}> */
    public array $entries = [];

    public function __construct(private readonly TrackingTransactionRunner $runner) {}

    public function record(string $actorId, string $action): void
    {
        $this->entries[] = ['action' => $action, 'inside' => $this->runner->inside];
    }
}
