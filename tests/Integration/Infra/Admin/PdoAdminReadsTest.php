<?php

declare(strict_types=1);

namespace LeCercle\Tests\Integration\Infra\Admin;

use LeCercle\Infra\Admin\PdoDashboardCounts;
use LeCercle\Infra\Admin\PdoJournalReader;
use LeCercle\Tests\Integration\DatabaseTestCase;

final class PdoAdminReadsTest extends DatabaseTestCase
{
    public function testJournalNewestFirstAndLimited(): void
    {
        $insert = self::pdo()->prepare('INSERT INTO admin_log (actor_id, action, created_at) VALUES (?, ?, ?)');
        $insert->execute(['root', 'premier', '2026-11-12 10:00:00.000']);
        $insert->execute(['root', 'deuxième', '2026-11-12 10:00:01.500']);
        $insert->execute(['root', 'même instant, plus tard', '2026-11-12 10:00:01.500']);

        $entries = (new PdoJournalReader(self::pdo()))->latest(2);

        self::assertSame(['même instant, plus tard', 'deuxième'], array_column($entries, 'action'));
        self::assertSame('2026-11-12 10:00:01.500', $entries[1]['at']->format('Y-m-d H:i:s.v'));
    }

    public function testCountsOnFixedData(): void
    {
        $pdo = self::pdo();
        $users = $pdo->prepare("INSERT INTO users (id, pseudo, email, provider, role, banned, created_at, last_seen_at) VALUES (?, ?, ?, 'local', 'user', 0, 1794474000000, ?)");
        $users->execute(['u1', 'alice', 'a@x.fr', '2026-11-12 10:00:00.000']);
        $users->execute(['u2', 'bob', 'b@x.fr', '2026-11-12 09:58:00.000']);
        $users->execute(['u3', 'carol', 'c@x.fr', null]);
        $games = $pdo->prepare("INSERT INTO games (id, mode, status, owner_id, white_id, created_at, updated_at) VALUES (?, ?, ?, 'u1', 'u1', '2026-11-12 09:00:00.000', '2026-11-12 09:00:00.000')");
        foreach ([['g1', 'online', 'open'], ['g2', 'online', 'playing'], ['g3', 'online', 'finished'], ['g4', 'online', 'finished'], ['g5', 'local', 'playing']] as $g) {
            $games->execute($g);
        }
        $pdo->exec("INSERT IGNORE INTO rooms (id, name, description, position, archived, created_by, created_at) VALUES ('rtest0000001', 'Test', '', 9, 0, NULL, '2026-11-12 09:00:00.000')");
        $pdo->exec("INSERT INTO room_messages (room_id, author_id, body, created_at) VALUES ('rtest0000001', 'u1', 'salut', '2026-11-12 09:00:00.000')");

        $counts = (new PdoDashboardCounts($pdo))->counts(new \DateTimeImmutable('2026-11-12 09:59:00.000', new \DateTimeZone('UTC')));

        self::assertSame(['accounts' => 3, 'online' => 1, 'open' => 1, 'playing' => 1, 'finished' => 2, 'roomMessages' => 1], $counts);
    }
}
