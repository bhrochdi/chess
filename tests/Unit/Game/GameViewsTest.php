<?php

declare(strict_types=1);

namespace LeCercle\Tests\Unit\Game;

use LeCercle\Chess\Ai\AiLevel;
use LeCercle\Chess\Color;
use LeCercle\Game\ChessRules;
use LeCercle\Game\Game;
use LeCercle\Game\GameSummary;
use LeCercle\Game\GameViews;
use LeCercle\Shared\Member;
use LeCercle\Shared\Millis;
use LeCercle\Shared\TimeControl;
use LeCercle\Tests\Support\CountingMembers;
use LeCercle\Tests\Support\InMemoryMembers;
use LeCercle\Tests\Support\MemberFactory;
use PHPUnit\Framework\TestCase;

final class GameViewsTest extends TestCase
{
    private const ALICE = 'u000000000001';
    private const BOB = 'u000000000002';
    private const CAROL = 'u000000000003';

    private \DateTimeImmutable $t0;
    private ChessRules $rules;
    private Member $alice;
    private Member $bob;
    private Member $carol;

    protected function setUp(): void
    {
        $this->t0 = new \DateTimeImmutable('2026-11-12 10:00:00.000', new \DateTimeZone('UTC'));
        $this->rules = ChessRules::standard();
        $this->alice = MemberFactory::user(self::ALICE, 'alice');
        $this->bob = MemberFactory::user(self::BOB, 'bob');
        $this->carol = MemberFactory::user(self::CAROL, 'carol');
    }

    private function at(int $ms): \DateTimeImmutable
    {
        return $this->t0->modify("+$ms milliseconds");
    }

    private function views(Member ...$members): GameViews
    {
        return new GameViews(new InMemoryMembers(...($members === [] ? [$this->alice, $this->bob] : $members)), $this->rules);
    }

    private function onlineAfterE4(): Game
    {
        $game = Game::online('g000000000001', self::ALICE, Color::White, new TimeControl(300, 0), $this->t0);
        $game->join(self::BOB, $this->t0);
        $game->play(self::ALICE, 'e2e4', $this->rules, $this->at(1000));

        return $game;
    }

    public function testOnlineGameForAPlayer(): void
    {
        $view = $this->views()->game($this->onlineAfterE4(), $this->bob, $this->at(2500));

        self::assertSame(
            ['id', 'mode', 'status', 'level', 'white', 'black', 'tc', 'fen', 'turn', 'moves', 'lastMove', 'legalMoves',
                'inCheck', 'captured', 'clock', 'drawOffer', 'result', 'reason', 'yourColor', 'createdAt', 'updatedAt'],
            array_keys($view),
        );
        self::assertSame('g000000000001', $view['id']);
        self::assertSame('online', $view['mode']);
        self::assertSame('playing', $view['status']);
        self::assertNull($view['level']);
        self::assertSame(['id' => self::ALICE, 'pseudo' => 'alice'], $view['white']);
        self::assertSame(['id' => self::BOB, 'pseudo' => 'bob'], $view['black']);
        self::assertSame(['base' => 300, 'inc' => 0], $view['tc']);
        self::assertSame('rnbqkbnr/pppppppp/8/8/4P3/8/PPPP1PPP/RNBQKBNR b KQkq e3 0 1', $view['fen']);
        self::assertSame('b', $view['turn']);
        self::assertSame(['e4'], $view['moves']);
        self::assertSame(['from' => 'e2', 'to' => 'e4'], $view['lastMove']);
        self::assertCount(20, $view['legalMoves']);
        self::assertContains('a7a6', $view['legalMoves']);
        self::assertFalse($view['inCheck']);
        self::assertSame(['w' => [], 'b' => []], $view['captured']);
        self::assertSame(
            ['whiteMs' => 299000, 'blackMs' => 298500, 'running' => 'b', 'serverNow' => Millis::of($this->at(2500))],
            $view['clock'],
        );
        self::assertNull($view['drawOffer']);
        self::assertNull($view['result']);
        self::assertNull($view['reason']);
        self::assertSame('b', $view['yourColor']);
        self::assertSame(Millis::of($this->t0), $view['createdAt']);
        self::assertSame(Millis::of($this->at(1000)), $view['updatedAt']);
    }

    public function testViewTakesTheGivenNow(): void
    {
        $game = $this->onlineAfterE4();

        $later = $this->views()->game($game, $this->bob, $this->at(4000))['clock'];

        self::assertSame(297000, $later['blackMs']);
        self::assertSame(Millis::of($this->at(4000)), $later['serverNow']);
    }

    public function testWaitingPlayerSeesNoLegalMovesWhenItIsNotHisTurn(): void
    {
        $view = $this->views()->game($this->onlineAfterE4(), $this->alice, $this->at(2500));

        self::assertSame('w', $view['yourColor']);
        self::assertSame([], $view['legalMoves']);
    }

    public function testSpectatorGetsNoLegalMoves(): void
    {
        $view = $this->views()->game($this->onlineAfterE4(), null, $this->at(2500));

        self::assertNull($view['yourColor']);
        self::assertSame([], $view['legalMoves']);
    }

    public function testStrangerIsASpectator(): void
    {
        $view = $this->views($this->alice, $this->bob, $this->carol)->game($this->onlineAfterE4(), $this->carol, $this->at(2500));

        self::assertNull($view['yourColor']);
        self::assertSame([], $view['legalMoves']);
    }

    public function testLocalGameShowsTheOwnerOnBothSides(): void
    {
        $game = Game::local('g000000000002', self::ALICE, null, $this->t0);

        $view = $this->views()->game($game, $this->alice, $this->t0);

        $ref = ['id' => self::ALICE, 'pseudo' => 'alice'];
        self::assertSame($ref, $view['white']);
        self::assertSame($ref, $view['black']);
        self::assertSame('both', $view['yourColor']);
        self::assertCount(20, $view['legalMoves']);
        self::assertNull($view['tc']);
        self::assertNull($view['clock']);
    }

    public function testComputerGameHasNoBlackPlayer(): void
    {
        $game = Game::againstComputer('g000000000003', self::ALICE, AiLevel::Beginner, null, $this->t0);

        $view = $this->views()->game($game, $this->alice, $this->t0);

        self::assertNull($view['black']);
        self::assertSame(['id' => self::ALICE, 'pseudo' => 'alice'], $view['white']);
        self::assertSame('ai-2', $view['level']);
        self::assertSame('ai', $view['mode']);
        self::assertSame('w', $view['yourColor']);
    }

    public function testEmptyGameHasNoLastMove(): void
    {
        $view = $this->views()->game(Game::local('g000000000002', self::ALICE, null, $this->t0), $this->alice, $this->t0);

        self::assertNull($view['lastMove']);
        self::assertSame([], $view['moves']);
    }

    public function testPromotionLastMoveKeepsOnlyTheSquares(): void
    {
        $game = Game::local('g000000000002', self::ALICE, null, $this->t0);
        foreach (['h2h4', 'g7g5', 'h4g5', 'h7h6', 'g5h6', 'g8f6', 'h6h7', 'f6g8', 'h7g8q'] as $uci) {
            $game->play(self::ALICE, $uci, $this->rules, $this->t0);
        }

        $view = $this->views()->game($game, $this->alice, $this->t0);

        self::assertSame(['from' => 'h7', 'to' => 'g8'], $view['lastMove']);
        self::assertSame(['w' => ['p', 'p', 'n'], 'b' => []], $view['captured']);
    }

    public function testDeletedAccountKeepsItsId(): void
    {
        $game = Game::online('g000000000001', self::ALICE, Color::White, null, $this->t0);
        $game->join(self::BOB, $this->t0);

        $view = $this->views($this->alice)->game($game, $this->alice, $this->t0);

        self::assertSame(['id' => self::BOB, 'pseudo' => '(compte supprimé)'], $view['black']);
    }

    public function testOpenGameHasAnEmptySeat(): void
    {
        $game = Game::online('g000000000001', self::ALICE, Color::Black, null, $this->t0);

        $view = $this->views()->game($game, $this->alice, $this->t0);

        self::assertNull($view['white']);
        self::assertSame('open', $view['status']);
        self::assertSame('b', $view['yourColor']);
        self::assertSame([], $view['legalMoves']);
    }

    public function testDrawOfferShowsTheOfferingColour(): void
    {
        $game = $this->onlineAfterE4();
        $game->offerDraw(self::BOB, $this->at(2000));

        self::assertSame('b', $this->views()->game($game, $this->alice, $this->at(2500))['drawOffer']);
    }

    public function testInCheck(): void
    {
        $game = Game::local('g000000000002', self::ALICE, null, $this->t0);
        foreach (['e2e4', 'f7f6', 'd1h5'] as $uci) {
            $game->play(self::ALICE, $uci, $this->rules, $this->t0);
        }

        self::assertTrue($this->views()->game($game, $this->alice, $this->t0)['inCheck']);
    }

    public function testFinishedGame(): void
    {
        $game = Game::local('g000000000002', self::ALICE, new TimeControl(300, 0), $this->t0);
        foreach (['e2e4', 'e7e5', 'f1c4', 'b8c6', 'd1h5', 'g8f6', 'h5f7'] as $uci) {
            $game->play(self::ALICE, $uci, $this->rules, $this->t0);
        }

        $view = $this->views()->game($game, $this->alice, $this->at(5000));

        self::assertSame('finished', $view['status']);
        self::assertSame('1-0', $view['result']);
        self::assertSame('mat', $view['reason']);
        self::assertSame([], $view['legalMoves']);
        self::assertNotNull($view['clock']);
        self::assertNull($view['clock']['running']);
        self::assertTrue($view['inCheck']);
        self::assertSame('both', $view['yourColor']);
    }

    public function testSummaries(): void
    {
        $online = $this->onlineAfterE4();
        $local = Game::local('g000000000002', self::CAROL, null, $this->t0);
        $views = $this->views($this->alice, $this->bob, $this->carol);

        $list = $views->summaries([GameSummary::of($online), GameSummary::of($local)]);

        self::assertSame(
            ['id', 'mode', 'status', 'white', 'black', 'level', 'tc', 'result', 'reason', 'updatedAt'],
            array_keys($list[0]),
        );
        self::assertSame('g000000000001', $list[0]['id']);
        self::assertSame(['id' => self::ALICE, 'pseudo' => 'alice'], $list[0]['white']);
        self::assertSame(['id' => self::BOB, 'pseudo' => 'bob'], $list[0]['black']);
        self::assertSame(['base' => 300, 'inc' => 0], $list[0]['tc']);
        self::assertSame(Millis::of($this->at(1000)), $list[0]['updatedAt']);
        self::assertNull($list[0]['result']);
        self::assertNull($list[0]['reason']);
        $carol = ['id' => self::CAROL, 'pseudo' => 'carol'];
        self::assertSame($carol, $list[1]['white']);
        self::assertSame($carol, $list[1]['black']);
        self::assertNull($list[1]['tc']);
    }

    public function testSummaryOfAComputerGameAndOfAFinishedOne(): void
    {
        $ai = Game::againstComputer('g000000000003', self::ALICE, AiLevel::Master, null, $this->t0);
        $done = $this->onlineAfterE4();
        $done->resign(self::BOB, $this->at(3000));

        [$a, $b] = $this->views()->summaries([GameSummary::of($ai), GameSummary::of($done)]);

        self::assertNull($a['black']);
        self::assertSame('ai-4', $a['level']);
        self::assertSame('finished', $b['status']);
        self::assertSame('1-0', $b['result']);
        self::assertSame('abandon', $b['reason']);
    }

    public function testSummariesAskForMembersOnce(): void
    {
        $spy = new CountingMembers(new InMemoryMembers($this->alice, $this->bob, $this->carol));
        $games = [];
        foreach ([[self::ALICE, self::BOB], [self::BOB, self::CAROL], [self::CAROL, self::ALICE]] as $i => [$w, $b]) {
            $g = Game::online("g00000000010$i", $w, Color::White, null, $this->t0);
            $g->join($b, $this->t0);
            $games[] = GameSummary::of($g);
        }
        $views = new GameViews($spy, $this->rules);

        $list = $views->summaries($games);

        self::assertSame(1, $spy->findManyCalls);
        self::assertSame('carol', $list[2]['white']['pseudo']);
        self::assertSame('bob', $list[1]['white']['pseudo']);

        $views->game($this->onlineAfterE4(), $this->alice, $this->t0);
        self::assertSame(2, $spy->findManyCalls);
    }

    public function testPlayerNames(): void
    {
        $views = $this->views();
        $open = Game::online('g000000000001', self::ALICE, Color::White, null, $this->t0);
        $local = Game::local('g000000000002', self::ALICE, null, $this->t0);
        $ai = Game::againstComputer('g000000000003', self::ALICE, AiLevel::Club, null, $this->t0);

        self::assertSame(['alice', '?'], $views->playerNames($open));
        self::assertSame(['alice', 'bob'], $views->playerNames($this->onlineAfterE4()));
        self::assertSame(['Joueur Blancs', 'Joueur Noirs'], $views->playerNames($local));
        self::assertSame(['Joueur Blancs', 'Ordinateur'], $views->playerNames($ai));
    }

    public function testPlayerNamesAskForMembersOnce(): void
    {
        $spy = new CountingMembers(new InMemoryMembers($this->alice, $this->bob));

        $names = (new GameViews($spy, $this->rules))->playerNames($this->onlineAfterE4());

        self::assertSame(['alice', 'bob'], $names);
        self::assertSame(1, $spy->findManyCalls);
    }

    public function testPlayerNamesOfADeletedAccount(): void
    {
        $game = $this->onlineAfterE4();

        self::assertSame(['alice', '(compte supprimé)'], $this->views($this->alice)->playerNames($game));
    }
}
