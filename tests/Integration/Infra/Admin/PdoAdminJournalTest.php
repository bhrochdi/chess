<?php

declare(strict_types=1);

namespace LeCercle\Tests\Integration\Infra\Admin;

use LeCercle\Infra\Admin\PdoAdminJournal;
use LeCercle\Tests\Integration\DatabaseTestCase;
use LeCercle\Tests\Support\FixedClock;

final class PdoAdminJournalTest extends DatabaseTestCase
{
    private static function query(string $sql): \PDOStatement
    {
        $statement = self::pdo()->query($sql);
        self::assertNotFalse($statement);

        return $statement;
    }

    private function journal(): PdoAdminJournal
    {
        $clock = new FixedClock(new \DateTimeImmutable('2026-11-12 10:00:00.000', new \DateTimeZone('UTC')));

        return new PdoAdminJournal(self::pdo(), $clock);
    }

    public function testRecordInsertsOneRow(): void
    {
        $this->journal()->record('u000000000009', 'Salon « Général » vidé');

        $rows = self::query('SELECT actor_id, action, created_at FROM admin_log')->fetchAll(\PDO::FETCH_ASSOC);

        self::assertSame(
            [['actor_id' => 'u000000000009', 'action' => 'Salon « Général » vidé', 'created_at' => '2026-11-12 10:00:00.000']],
            $rows,
        );
    }

    public function testActionOver500CharactersIsRejected(): void
    {
        try {
            $this->journal()->record('u000000000009', str_repeat('é', 501));
            self::fail('InvalidArgumentException attendue.');
        } catch (\InvalidArgumentException) {
            $count = self::query('SELECT COUNT(*) FROM admin_log')->fetchColumn();
            self::assertSame(0, (int) $count);
        }
    }

    public function testAction500CharactersIsAccepted(): void
    {
        $this->journal()->record('u000000000009', str_repeat('é', 500));

        self::assertSame(1, (int) self::query('SELECT COUNT(*) FROM admin_log')->fetchColumn());
    }
}
