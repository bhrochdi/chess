<?php

declare(strict_types=1);

namespace LeCercle\Tests\Unit\Game;

use LeCercle\Game\GameService;
use LeCercle\Shared\ForbiddenException;
use LeCercle\Shared\NotFoundException;
use LeCercle\Shared\ValidationException;
use PHPUnit\Framework\TestCase;

/** Historique des parties terminées d'un joueur et relecture coup par coup. */
final class GameHistoryTest extends TestCase
{
    use GameServiceFixture;

    private GameService $service;

    protected function setUp(): void
    {
        $this->setUpFixture();
        $this->service = $this->service();
    }

    /** Partie en ligne alice (blancs) contre bob, close par l'administration ; renvoie son identifiant. */
    private function finishedGame(?string $result = '1-0'): string
    {
        $id = $this->onlineGame($this->service);
        $this->as(self::ROOT);
        $this->service->adminClose($id, $result);
        // Une milliseconde entre deux fins : l'ordre de l'historique est celui des fins.
        $this->clock->advanceMs(1);

        return $id;
    }

    public function testHistoryListsTheCallersFinishedOnlineGamesNewestFirst(): void
    {
        $first = $this->finishedGame();
        $second = $this->finishedGame('0-1');
        $this->onlineGame($this->service);
        $this->as(self::ALICE);
        $local = $this->service->create('local', null, null, null)['id'];
        $this->as(self::ROOT);
        $this->service->adminClose($local, '1-0');

        $this->as(self::ALICE);
        $history = $this->service->history(0);

        self::assertSame([$second, $first], array_column($history['games'], 'id'));
        self::assertSame(['0-1', '1-0'], array_column($history['games'], 'result'));
        self::assertFalse($history['more']);
    }

    public function testEachPlayerSeesOnlyTheirOwnGames(): void
    {
        $id = $this->finishedGame();

        $this->as(self::BOB);
        self::assertSame([$id], array_column($this->service->history(0)['games'], 'id'));
        $this->as(self::CAROL);
        self::assertSame(['games' => [], 'more' => false], $this->service->history(0));
    }

    public function testHistoryIsPagedByTwenty(): void
    {
        $ids = [];
        for ($i = 0; $i < GameService::HISTORY_LIMIT + 1; ++$i) {
            $ids[] = $this->finishedGame();
            // Une fenêtre de création entière entre deux défis : la limite de débit ne s'applique pas.
            $this->clock->advanceMs(GameService::CREATE_WINDOW_MS);
        }
        $this->as(self::ALICE);

        $page = $this->service->history(0);
        self::assertCount(GameService::HISTORY_LIMIT, $page['games']);
        self::assertTrue($page['more']);
        self::assertSame($ids[GameService::HISTORY_LIMIT], $page['games'][0]['id']);

        $next = $this->service->history(GameService::HISTORY_LIMIT);
        self::assertSame([$ids[0]], array_column($next['games'], 'id'));
        self::assertFalse($next['more']);
    }

    public function testHistoryNeedsAnAccountAndAValidOffset(): void
    {
        try {
            $this->service->history(0);
            self::fail('Connexion requise.');
        } catch (ForbiddenException $e) {
            self::assertSame('Connexion requise.', $e->getMessage());
        }

        $this->as(self::ALICE);
        foreach ([-1, GameService::HISTORY_MAX_OFFSET + 1] as $offset) {
            try {
                $this->service->history($offset);
                self::fail("Décalage $offset accepté.");
            } catch (ValidationException $e) {
                self::assertSame('Page d’historique invalide.', $e->getMessage());
            }
        }
    }

    public function testReplayGivesOneFramePerPlyFromTheStartingPosition(): void
    {
        $id = $this->onlineGame($this->service, ['base' => 300, 'inc' => 2]);
        $plays = [[self::ALICE, 'e2e4'], [self::BOB, 'd7d5'], [self::ALICE, 'e4d5'], [self::BOB, 'd8d5']];
        foreach ($plays as $ply => [$who, $uci]) {
            $this->as($who);
            $this->clock->advanceMs(1000);
            $this->service->move($id, $uci, $ply);
        }

        $replay = $this->service->replay($id);

        self::assertSame($id, $replay['id']);
        $frames = $replay['frames'];
        self::assertCount(5, $frames);
        self::assertSame('rnbqkbnr/pppppppp/8/8/8/8/PPPPPPPP/RNBQKBNR w KQkq - 0 1', $frames[0]['fen']);
        self::assertNull($frames[0]['lastMove']);
        self::assertSame(['whiteMs' => 300_000, 'blackMs' => 300_000], $frames[0]['clock']);
        self::assertSame(['from' => 'e2', 'to' => 'e4'], $frames[1]['lastMove']);
        self::assertSame(['w' => ['p'], 'b' => []], $frames[3]['captured']);
        self::assertSame(['w' => ['p'], 'b' => ['P']], $frames[4]['captured']);
        self::assertSame($this->service->get($id)['fen'], $frames[4]['fen']);
        self::assertSame(['fen', 'inCheck', 'lastMove', 'captured', 'clock'], array_keys($frames[4]));
    }

    public function testReplayMarksChecksAndKeepsTheClocksAfterEachMove(): void
    {
        $id = $this->onlineGame($this->service, ['base' => 60, 'inc' => 0]);
        $plays = [[self::ALICE, 'e2e4'], [self::BOB, 'f7f6'], [self::ALICE, 'd1h5']];
        foreach ($plays as $ply => [$who, $uci]) {
            $this->as($who);
            $this->clock->advanceMs(2000);
            $this->service->move($id, $uci, $ply);
        }

        $frames = $this->service->replay($id)['frames'];

        self::assertSame([false, false, false, true], array_column($frames, 'inCheck'));
        // La pendule des Blancs part quand Bob rejoint le défi : chacun perd ses deux secondes de réflexion par coup.
        self::assertSame(['whiteMs' => 58_000, 'blackMs' => 60_000], $frames[1]['clock']);
        self::assertSame(['whiteMs' => 58_000, 'blackMs' => 58_000], $frames[2]['clock']);
        self::assertSame(['whiteMs' => 56_000, 'blackMs' => 58_000], $frames[3]['clock']);
    }

    public function testReplayWithoutClockHasNoTimes(): void
    {
        $this->as(self::ALICE);
        $id = $this->service->create('local', null, null, null)['id'];
        $this->service->move($id, 'e2e4', 0);

        self::assertSame([null, null], array_column($this->service->replay($id)['frames'], 'clock'));
    }

    public function testAnOnlineGameCanBeReplayedBySpectatorsButALocalOneOnlyByItsOwner(): void
    {
        $online = $this->onlineGame($this->service);
        $this->as(self::ALICE);
        $local = $this->service->create('local', null, null, null)['id'];

        $this->as(null);
        self::assertCount(1, $this->service->replay($online)['frames']);
        $this->as(self::BOB);

        $this->expectException(NotFoundException::class);
        $this->expectExceptionMessage('Partie introuvable.');
        $this->service->replay($local);
    }

    public function testReplayDoesNotChangeTheGame(): void
    {
        $id = $this->onlineGame($this->service);
        $this->service->move($id, 'e2e4', 0);
        $before = $this->service->get($id);

        $this->service->replay($id);

        self::assertSame($before, $this->service->get($id));
    }
}
