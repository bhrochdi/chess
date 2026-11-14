<?php

declare(strict_types=1);

namespace LeCercle\Tests\Integration\Infra\Chat;

use LeCercle\Tests\Integration\DatabaseTestCase;

final class ChatMigrationsTest extends DatabaseTestCase
{
    /** @return list<mixed> */
    private static function column(string $sql): array
    {
        $statement = self::pdo()->query($sql);
        self::assertNotFalse($statement);

        return array_values($statement->fetchAll(\PDO::FETCH_COLUMN));
    }

    public function testChatTablesExist(): void
    {
        $tables = self::column('SHOW TABLES');

        foreach (['rooms', 'room_messages', 'direct_messages', 'dm_reads', 'game_comments'] as $table) {
            self::assertContains($table, $tables);
        }
    }

    public function testMigrationsAreRecorded(): void
    {
        $names = self::column('SELECT name FROM schema_migrations');

        foreach (['200-salons', '201-messages-prives', '202-commentaires-parties'] as $name) {
            self::assertContains($name, $names);
        }
    }

    public function testGeneralSeedIsIdempotent(): void
    {
        $sql = (string) file_get_contents(__DIR__ . '/../../../../sql/migrations/200-salons.sql');
        self::assertSame(1, preg_match('/INSERT IGNORE INTO rooms.*?;/s', $sql, $match));
        $seed = $match[0] ?? '';

        self::pdo()->exec($seed);
        self::pdo()->exec($seed);

        self::assertSame(['general'], self::column("SELECT id FROM rooms WHERE name = 'Général'"));
        self::assertSame(['1'], array_map('strval', self::column('SELECT COUNT(*) FROM rooms')));
    }
}
