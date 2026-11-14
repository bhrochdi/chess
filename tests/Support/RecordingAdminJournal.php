<?php

declare(strict_types=1);

namespace LeCercle\Tests\Support;

use LeCercle\Shared\AdminJournal;

/** Journal de test : garde les entrées en mémoire. */
final class RecordingAdminJournal implements AdminJournal
{
    /** @var list<array{actor: string, action: string}> */
    public array $entries = [];

    public function record(string $actorId, string $action): void
    {
        $this->entries[] = ['actor' => $actorId, 'action' => $action];
    }
}
