<?php

declare(strict_types=1);

namespace LeCercle\Tests\Integration\Infra\Game;

use LeCercle\Chess\Result;
use LeCercle\Infra\Game\PdoResultArchive;
use LeCercle\Tests\Integration\DatabaseTestCase;

final class PdoResultArchiveTest extends DatabaseTestCase
{
    private function insertUser(string $id): void
    {
        $stmt = self::pdo()->prepare(
            'INSERT INTO users (id, pseudo, email, provider, role, pass_hash, banned, created_at)
             VALUES (?, ?, ?, ?, ?, NULL, 0, ?)',
        );
        $stmt->execute([$id, $id, $id . '@x.fr', 'local', 'user', 1794477601500]);
    }

    /** @return array<string, array{int, int, int}> compte => victoires, défaites, nulles */
    private function rows(): array
    {
        $stmt = self::pdo()->query('SELECT user_id, wins, losses, draws FROM archived_stats ORDER BY user_id');
        self::assertNotFalse($stmt);
        $rows = [];
        foreach ($stmt->fetchAll() as $row) {
            $rows[(string) $row['user_id']] = [(int) $row['wins'], (int) $row['losses'], (int) $row['draws']];
        }

        return $rows;
    }

    public function testKeepCumulatesResultsPerPlayer(): void
    {
        $this->insertUser('alice');
        $this->insertUser('bob');
        $archive = new PdoResultArchive(self::pdo());

        $archive->keep('alice', 'bob', Result::WhiteWins);
        $archive->keep('bob', 'alice', Result::WhiteWins);
        $archive->keep('alice', 'bob', Result::BlackWins);
        $archive->keep('alice', 'bob', Result::Draw);

        self::assertSame(['alice' => [1, 2, 1], 'bob' => [2, 1, 1]], $this->rows());
    }

    public function testKeepIgnoresADeletedAccount(): void
    {
        $this->insertUser('alice');

        (new PdoResultArchive(self::pdo()))->keep('alice', 'gone', Result::WhiteWins);

        self::assertSame(['alice' => [1, 0, 0]], $this->rows());
    }

    public function testArchivedStatsLeaveWithTheAccount(): void
    {
        $this->insertUser('alice');
        $this->insertUser('bob');
        (new PdoResultArchive(self::pdo()))->keep('alice', 'bob', Result::Draw);

        self::pdo()->prepare('DELETE FROM users WHERE id = ?')->execute(['alice']);

        self::assertSame(['bob' => [0, 0, 1]], $this->rows());
    }
}
