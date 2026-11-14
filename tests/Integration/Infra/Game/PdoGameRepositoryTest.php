<?php

declare(strict_types=1);

namespace LeCercle\Tests\Integration\Infra\Game;

use LeCercle\Chess\Ai\AiLevel;
use LeCercle\Chess\Color;
use LeCercle\Chess\Result;
use LeCercle\Game\ChessRules;
use LeCercle\Game\Game;
use LeCercle\Game\GameMode;
use LeCercle\Game\GameSnapshot;
use LeCercle\Game\GameStatus;
use LeCercle\Infra\Db\PdoFactory;
use LeCercle\Infra\Game\PdoGameRepository;
use LeCercle\Shared\NotFoundException;
use LeCercle\Shared\TimeControl;
use LeCercle\Tests\Integration\DatabaseTestCase;
use LeCercle\Tests\Integration\TestDatabase;

final class PdoGameRepositoryTest extends DatabaseTestCase
{
    private const FORMAT = 'Y-m-d H:i:s.v';

    private ChessRules $rules;
    private PdoGameRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->rules = ChessRules::standard();
        $this->repo = new PdoGameRepository(self::pdo(), $this->rules);
    }

    private static function at(string $time): \DateTimeImmutable
    {
        return new \DateTimeImmutable($time, new \DateTimeZone('UTC'));
    }

    private static function t0(): \DateTimeImmutable
    {
        return self::at('2026-11-12 10:00:00.000');
    }

    private function onlineGame(string $id, string $creator, \DateTimeImmutable $now, ?TimeControl $tc = null): Game
    {
        return Game::online($id, $creator, Color::White, $tc, $now);
    }

    /** Partie en ligne jouée : alice (blancs) contre bob, quatre coups, une seconde et demie entre chaque. */
    private function playedOnlineGame(string $id): Game
    {
        $t0 = self::t0();
        $game = $this->onlineGame($id, 'alice', $t0, new TimeControl(300, 2));
        $game->join('bob', $t0->modify('+1 second'));
        $game->play('alice', 'e2e4', $this->rules, $t0->modify('+2 seconds 500 ms'));
        $game->play('bob', 'e7e5', $this->rules, $t0->modify('+4 seconds'));
        $game->play('alice', 'g1f3', $this->rules, $t0->modify('+5 seconds 500 ms'));
        $game->play('bob', 'b8c6', $this->rules, $t0->modify('+7 seconds 250 ms'));

        return $game;
    }

    private function assertSameSnapshot(GameSnapshot $expected, GameSnapshot $actual): void
    {
        self::assertSame($expected->id, $actual->id);
        self::assertSame($expected->mode, $actual->mode);
        self::assertSame($expected->ownerId, $actual->ownerId);
        self::assertSame($expected->whiteId, $actual->whiteId);
        self::assertSame($expected->blackId, $actual->blackId);
        self::assertSame($expected->level, $actual->level);
        self::assertEquals($expected->timeControl, $actual->timeControl);
        self::assertSame($expected->status, $actual->status);
        self::assertSame($expected->drawOffer, $actual->drawOffer);
        self::assertSame($expected->result, $actual->result);
        self::assertSame($expected->reason, $actual->reason);
        self::assertSame($expected->createdAt->format(self::FORMAT), $actual->createdAt->format(self::FORMAT));
        self::assertSame($expected->updatedAt->format(self::FORMAT), $actual->updatedAt->format(self::FORMAT));

        self::assertSame($expected->clock === null, $actual->clock === null);
        if ($expected->clock !== null && $actual->clock !== null) {
            self::assertSame($expected->clock->whiteMs, $actual->clock->whiteMs);
            self::assertSame($expected->clock->blackMs, $actual->clock->blackMs);
            self::assertSame($expected->clock->incrementMs, $actual->clock->incrementMs);
            self::assertSame($expected->clock->running, $actual->clock->running);
            self::assertSame($expected->clock->since?->format(self::FORMAT), $actual->clock->since?->format(self::FORMAT));
        }

        self::assertCount(\count($expected->moves), $actual->moves);
        foreach ($expected->moves as $i => $move) {
            self::assertSame($move->uci, $actual->moves[$i]->uci);
            self::assertSame($move->san, $actual->moves[$i]->san);
            self::assertSame($move->whiteMs, $actual->moves[$i]->whiteMs);
            self::assertSame($move->blackMs, $actual->moves[$i]->blackMs);
            self::assertSame($move->playedAt->format(self::FORMAT), $actual->moves[$i]->playedAt->format(self::FORMAT));
        }
    }

    private function moveCount(string $id): int
    {
        $stmt = self::pdo()->prepare('SELECT COUNT(*) FROM game_moves WHERE game_id = ?');
        $stmt->execute([$id]);

        return (int) $stmt->fetchColumn();
    }

    public function testNextIdFormat(): void
    {
        $a = $this->repo->nextId();
        $b = $this->repo->nextId();

        self::assertMatchesRegularExpression('/\Ag[0-9a-f]{12}\z/', $a);
        self::assertNotSame($a, $b);
    }

    public function testRoundTripOfAPlayedOnlineGame(): void
    {
        $id = $this->repo->nextId();
        $game = $this->playedOnlineGame($id);
        $this->repo->add($this->onlineGame($id, 'alice', self::t0(), new TimeControl(300, 2)));
        $this->repo->save($game);

        $loaded = $this->repo->get($id);

        $this->assertSameSnapshot($game->snapshot(), $loaded->snapshot());
        self::assertSame(4, $loaded->plyCount());
        self::assertSame(Color::White, $loaded->position()->sideToMove());
        $clock = $loaded->snapshot()->clock;
        self::assertNotNull($clock);
        self::assertSame(2000, $clock->incrementMs);
        self::assertSame(Color::White, $clock->running);
    }

    public function testRoundTripOfAComputerGame(): void
    {
        $id = $this->repo->nextId();
        $game = Game::againstComputer($id, 'alice', AiLevel::Club, new TimeControl(60, 0), self::t0());
        $game->play('alice', 'e2e4', $this->rules, self::t0()->modify('+1 second 20 ms'));
        $this->repo->add($game);

        $loaded = $this->repo->get($id);

        $this->assertSameSnapshot($game->snapshot(), $loaded->snapshot());
        self::assertSame(AiLevel::Club, $loaded->level());
        self::assertNull($loaded->blackId());
        self::assertSame('alice', $loaded->whiteId());
        self::assertTrue($loaded->computerToMove());
        $row = self::pdo()->query("SELECT ai_level, mode FROM games WHERE id = '$id'");
        self::assertNotFalse($row);
        self::assertSame(['ai_level' => 'ai-3', 'mode' => 'ai'], $row->fetch());
    }

    public function testRoundTripOfAnUntimedGameWithoutClock(): void
    {
        $id = $this->repo->nextId();
        $game = Game::local($id, 'alice', null, self::t0());
        $this->repo->add($game);

        $loaded = $this->repo->get($id);

        $this->assertSameSnapshot($game->snapshot(), $loaded->snapshot());
        self::assertNull($loaded->timeControl());
        self::assertNull($loaded->clock());
        self::assertSame(GameMode::Local, $loaded->mode());
        $row = self::pdo()->query("SELECT tc_base, tc_inc, white_ms, black_ms, clock_running FROM games WHERE id = '$id'");
        self::assertNotFalse($row);
        self::assertSame(['tc_base' => null, 'tc_inc' => null, 'white_ms' => null, 'black_ms' => null, 'clock_running' => null], $row->fetch());
    }

    public function testRoundTripKeepsDrawOfferAndFinish(): void
    {
        $offered = $this->playedOnlineGame($this->repo->nextId());
        $offered->offerDraw('alice', self::t0()->modify('+8 seconds'));
        $resigned = $this->playedOnlineGame($this->repo->nextId());
        $resigned->resign('bob', self::t0()->modify('+9 seconds 125 ms'));
        foreach ([$offered, $resigned] as $game) {
            $this->repo->add($this->onlineGame($game->id(), 'alice', self::t0(), new TimeControl(300, 2)));
            $this->repo->save($game);
        }

        $this->assertSameSnapshot($offered->snapshot(), $this->repo->get($offered->id())->snapshot());
        self::assertSame(Color::White, $this->repo->get($offered->id())->drawOffer());
        $this->assertSameSnapshot($resigned->snapshot(), $this->repo->get($resigned->id())->snapshot());
        $loaded = $this->repo->get($resigned->id());
        self::assertSame(GameStatus::Finished, $loaded->status());
        $clock = $loaded->clock();
        self::assertNotNull($clock);
        self::assertNull($clock->running);
        self::assertNull($clock->since);
    }

    public function testSaveInsertsOnlyTheNewPlies(): void
    {
        $id = $this->repo->nextId();
        $game = $this->onlineGame($id, 'alice', self::t0(), new TimeControl(300, 2));
        $this->repo->add($game);
        $game->join('bob', self::t0()->modify('+1 second'));
        $game->play('alice', 'e2e4', $this->rules, self::t0()->modify('+2 seconds'));
        $this->repo->save($game);

        $game = $this->repo->getForUpdate($id);
        $game->play('bob', 'e7e5', $this->rules, self::t0()->modify('+3 seconds'));
        $this->repo->save($game);
        $this->repo->save($game);

        self::assertSame(['e2e4', 'e7e5'], array_map(static fn($m): string => $m->uci, $this->repo->get($id)->moves()));
        self::assertSame(2, $this->moveCount($id));
    }

    public function testUnknownIdIsNotFound(): void
    {
        $this->expectException(NotFoundException::class);
        $this->expectExceptionMessage('Partie introuvable.');

        $this->repo->get('g000000000000');
    }

    public function testGetForUpdateOnUnknownIdIsNotFound(): void
    {
        $this->expectException(NotFoundException::class);
        $this->expectExceptionMessage('Partie introuvable.');

        $this->repo->getForUpdate('g000000000000');
    }

    public function testSaveAfterUndoDeletesPlies(): void
    {
        $id = $this->repo->nextId();
        $game = Game::local($id, 'alice', new TimeControl(300, 0), self::t0());
        $this->repo->add($game);
        $game->play('alice', 'e2e4', $this->rules, self::t0()->modify('+1 second'));
        $game->play('alice', 'e7e5', $this->rules, self::t0()->modify('+2 seconds'));
        $game->play('alice', 'g1f3', $this->rules, self::t0()->modify('+3 seconds'));
        $this->repo->save($game);

        $game = $this->repo->getForUpdate($id);
        $game->undo('alice', $this->rules, self::t0()->modify('+4 seconds'));
        $this->repo->save($game);

        self::assertSame(2, $this->moveCount($id));
        $loaded = $this->repo->get($id);
        self::assertSame(2, $loaded->plyCount());
        $this->assertSameSnapshot($game->snapshot(), $loaded->snapshot());

        // Les plis supprimés se rejouent ensuite sans collision de clé.
        $loaded->play('alice', 'd2d4', $this->rules, self::t0()->modify('+5 seconds'));
        $this->repo->save($loaded);
        self::assertSame(['e2e4', 'e7e5', 'd2d4'], array_map(static fn($m): string => $m->uci, $this->repo->get($id)->moves()));
    }

    public function testGetForUpdateLocksTheRow(): void
    {
        $id = $this->repo->nextId();
        $this->repo->add($this->onlineGame($id, 'alice', self::t0()));

        self::pdo()->beginTransaction();
        try {
            $this->repo->getForUpdate($id);
            $second = PdoFactory::create(TestDatabase::config());
            try {
                $second->query("SELECT id FROM games WHERE id = '$id' FOR UPDATE NOWAIT");
                self::fail('La ligne aurait dû être verrouillée.');
            } catch (\PDOException $e) {
                self::assertSame('HY000', $e->getCode());
                self::assertSame(3572, $e->errorInfo[1] ?? null);
            }
        } finally {
            self::pdo()->rollBack();
        }
    }

    public function testGetDoesNotLockTheRow(): void
    {
        $id = $this->repo->nextId();
        $this->repo->add($this->onlineGame($id, 'alice', self::t0()));

        self::pdo()->beginTransaction();
        try {
            $this->repo->get($id);
            $second = PdoFactory::create(TestDatabase::config());
            $stmt = $second->query("SELECT id FROM games WHERE id = '$id' FOR UPDATE NOWAIT");
            self::assertNotFalse($stmt);
            self::assertSame($id, $stmt->fetchColumn());
        } finally {
            self::pdo()->rollBack();
        }
    }

    public function testLobby(): void
    {
        $now = self::at('2026-11-12 12:00:00.000');
        $open = $this->onlineGame('g000000000001', 'alice', $now);
        $playing = $this->onlineGame('g000000000002', 'alice', $now->modify('-30 minutes'));
        $playing->join('bob', $now->modify('-30 minutes'));
        $recent = $this->playedOnlineGame('g000000000003');
        $recent->resign('bob', $now->modify('-1 hour'));
        $old = $this->playedOnlineGame('g000000000004');
        $old->resign('bob', $now->modify('-3 hours'));
        $local = Game::local('g000000000005', 'alice', null, $now);
        foreach ([$old, $local, $recent, $playing, $open] as $game) {
            $this->repo->add($game);
        }

        $ids = array_map(static fn($s): string => $s->id, $this->repo->lobby($now->modify('-2 hours'), 30));

        self::assertSame(['g000000000001', 'g000000000002', 'g000000000003'], $ids);
        self::assertCount(2, $this->repo->lobby($now->modify('-2 hours'), 2));
        self::assertSame(['g000000000001', 'g000000000002'], array_map(static fn($s): string => $s->id, $this->repo->lobby($now->modify('-2 hours'), 2)));
    }

    public function testLobbyMapsSummaryFields(): void
    {
        $game = $this->playedOnlineGame('g000000000009');
        $game->resign('bob', self::t0()->modify('+10 seconds'));
        $this->repo->add($game);

        $summaries = $this->repo->lobby(self::t0(), 30);

        self::assertCount(1, $summaries);
        $s = $summaries[0];
        self::assertSame(GameMode::Online, $s->mode);
        self::assertSame(GameStatus::Finished, $s->status);
        self::assertSame('alice', $s->ownerId);
        self::assertSame('alice', $s->whiteId);
        self::assertSame('bob', $s->blackId);
        self::assertEquals(new TimeControl(300, 2), $s->timeControl);
        self::assertSame($game->result(), $s->result);
        self::assertSame($game->reason(), $s->reason);
        self::assertSame('2026-11-12 10:00:10.000', $s->updatedAt->format(self::FORMAT));
    }

    public function testLobbyIncludesGamesFinishedExactlyAtTheCutoff(): void
    {
        $cutoff = self::at('2026-11-12 10:00:00.000');
        $atCutoff = $this->playedOnlineGame('g000000000001');
        $atCutoff->resign('bob', $cutoff);
        $before = $this->playedOnlineGame('g000000000002');
        $before->resign('bob', $cutoff->modify('-1 millisecond'));
        $this->repo->add($atCutoff);
        $this->repo->add($before);

        $ids = array_map(static fn($s): string => $s->id, $this->repo->lobby($cutoff, 30));

        self::assertSame(['g000000000001'], $ids);
    }

    public function testLobbyTiesBreakOnIdAscending(): void
    {
        $now = self::at('2026-11-12 12:00:00.000');
        foreach (['g000000000003', 'g000000000001', 'g000000000002'] as $id) {
            $this->repo->add($this->onlineGame($id, 'alice', $now));
        }

        $ids = array_map(static fn($s): string => $s->id, $this->repo->lobby($now->modify('-1 hour'), 2));

        self::assertSame(['g000000000001', 'g000000000002'], $ids);
    }

    public function testUnfinishedOf(): void
    {
        $now = self::at('2026-11-12 12:00:00.000');
        $ownLocal = Game::local('g000000000001', 'alice', null, $now->modify('-5 minutes'));
        $finished = Game::local('g000000000002', 'alice', null, $now);
        $finished->play('alice', 'f2f3', $this->rules, $now);
        $finished->play('alice', 'e7e5', $this->rules, $now);
        $finished->play('alice', 'g2g4', $this->rules, $now);
        $finished->play('alice', 'd8h4', $this->rules, $now);
        self::assertSame(GameStatus::Finished, $finished->status());
        $asBlack = Game::online('g000000000003', 'bob', Color::White, null, $now->modify('-1 minute'));
        $asBlack->join('alice', $now->modify('-1 minute'));
        $strangers = Game::local('g000000000004', 'carol', null, $now);
        $open = Game::online('g000000000005', 'carol', Color::Black, null, $now);
        foreach ([$ownLocal, $finished, $asBlack, $strangers, $open] as $game) {
            $this->repo->add($game);
        }

        $summaries = $this->repo->unfinishedOf('alice', 30);

        self::assertSame(['g000000000003', 'g000000000001'], array_map(static fn($s): string => $s->id, $summaries));
        self::assertSame('bob', $summaries[0]->whiteId);
        self::assertSame('alice', $summaries[0]->blackId);
        self::assertCount(1, $this->repo->unfinishedOf('alice', 1));
    }

    public function testUnfinishedOfCountsOpenGamesOfTheirCreator(): void
    {
        $now = self::at('2026-11-12 12:00:00.000');
        $this->repo->add(Game::online('g000000000001', 'carol', Color::Black, null, $now));

        $summaries = $this->repo->unfinishedOf('carol', 30);

        self::assertCount(1, $summaries);
        self::assertSame(GameStatus::Open, $summaries[0]->status);
        self::assertSame('carol', $summaries[0]->blackId);
    }

    public function testUnfinishedOfTiesBreakOnIdAscending(): void
    {
        $now = self::at('2026-11-12 12:00:00.000');
        foreach (['g000000000002', 'g000000000003', 'g000000000001'] as $id) {
            $this->repo->add(Game::local($id, 'alice', null, $now));
        }

        $ids = array_map(static fn($s): string => $s->id, $this->repo->unfinishedOf('alice', 2));

        self::assertSame(['g000000000001', 'g000000000002'], $ids);
    }

    public function testDeletingAGameDeletesItsMoves(): void
    {
        $id = $this->repo->nextId();
        $this->repo->add($this->onlineGame($id, 'alice', self::t0(), new TimeControl(300, 2)));
        $this->repo->save($this->playedOnlineGame($id));
        self::assertSame(4, $this->moveCount($id));

        $stmt = self::pdo()->prepare('DELETE FROM games WHERE id = ?');
        $stmt->execute([$id]);

        self::assertSame(0, $this->moveCount($id));
    }

    public function testDeleteRemovesCommentsAndUnlinksPairings(): void
    {
        $id = 'g0000000000d1';
        $this->repo->add($this->onlineGame($id, 'alice', self::t0()));
        $pdo = self::pdo();
        $pdo->prepare('INSERT INTO game_comments (game_id, author_id, body, created_at) VALUES (?, ?, ?, ?)')
            ->execute([$id, 'bob', 'joli', '2026-11-12 10:00:00.000']);
        $pdo->exec("INSERT INTO tournaments (id, name, format, status, registration_open, current_round, created_by, created_at)
            VALUES ('t0000000000d1', 'Open', 'round-robin', 'en-cours', 0, 1, 'root', '2026-11-12 10:00:00.000')");
        $pdo->prepare("INSERT INTO tournament_pairings (tournament_id, round, board, white_id, black_id, result, game_id)
            VALUES ('t0000000000d1', 1, 1, 'alice', 'bob', '1-0', ?)")->execute([$id]);

        $this->repo->delete($id);

        self::assertSame(0, (int) $this->select('SELECT COUNT(*) FROM game_comments WHERE game_id = ?', $id)->fetchColumn());
        self::assertSame(0, (int) $this->select('SELECT COUNT(*) FROM games WHERE id = ?', $id)->fetchColumn());
        $pairing = $this->select('SELECT game_id, result FROM tournament_pairings WHERE tournament_id = ?', 't0000000000d1')->fetch();
        self::assertIsArray($pairing);
        self::assertSame([null, '1-0'], [$pairing['game_id'], $pairing['result']]);
    }

    private function select(string $sql, string $param): \PDOStatement
    {
        $stmt = self::pdo()->prepare($sql);
        $stmt->execute([$param]);

        return $stmt;
    }

    public function testFinishedIds(): void
    {
        $now = self::t0();
        $finished = Game::local('g0000000000d2', 'alice', null, $now);
        $finished->closeByAdmin(Result::Draw, $now);
        $this->repo->add($finished);
        $this->repo->add(Game::local('g0000000000d3', 'alice', null, $now));

        self::assertSame(['g0000000000d2'], $this->repo->finishedIds());
    }

    public function testAllIncludesEveryModeNewestFirst(): void
    {
        $now = self::t0();
        $this->repo->add(Game::local('g0000000000d4', 'alice', null, $now));
        $this->repo->add($this->onlineGame('g0000000000d5', 'bob', $now->modify('+1 second')));

        self::assertSame(['g0000000000d5', 'g0000000000d4'], array_map(static fn($s): string => $s->id, $this->repo->all(10)));
        self::assertCount(1, $this->repo->all(1));
    }
}
