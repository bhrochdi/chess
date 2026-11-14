<?php

declare(strict_types=1);

namespace LeCercle\Tests\Unit\Support;

use LeCercle\Tests\Support\RecordingAdminJournal;
use PHPUnit\Framework\TestCase;

final class RecordingAdminJournalTest extends TestCase
{
    public function testRecordsEntriesInOrder(): void
    {
        $journal = new RecordingAdminJournal();

        $journal->record('u9', 'Création du salon « Analyses »');

        self::assertSame([['actor' => 'u9', 'action' => 'Création du salon « Analyses »']], $journal->entries);
    }
}
