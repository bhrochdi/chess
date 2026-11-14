<?php

declare(strict_types=1);

namespace LeCercle\Tests\Unit\Game;

use LeCercle\Chess\Ai\AiLevel;
use LeCercle\Chess\Color;
use LeCercle\Chess\Position;
use LeCercle\Chess\Result;
use LeCercle\Game\ChessRules;
use LeCercle\Game\FinishReason;
use LeCercle\Game\GameClock;
use LeCercle\Game\Game;
use LeCercle\Game\GameMode;
use LeCercle\Game\GameSnapshot;
use LeCercle\Game\GameStatus;
use LeCercle\Game\PlayedMove;
use LeCercle\Shared\ConflictException;
use LeCercle\Shared\ForbiddenException;
use LeCercle\Shared\TimeControl;
use PHPUnit\Framework\TestCase;

final class GamePlayTest extends TestCase
{
    private const ALICE = 'u000000000001';
    private const BOB = 'u000000000002';
    private const SCHOLARS_MATE = ['e2e4', 'e7e5', 'f1c4', 'b8c6', 'd1h5', 'g8f6', 'h5f7'];

    private \DateTimeImmutable $t0;
    private ChessRules $rules;

    protected function setUp(): void
    {
        $this->t0 = new \DateTimeImmutable('2026-11-12 10:00:00.000', new \DateTimeZone('UTC'));
        $this->rules = ChessRules::standard();
    }

    private function at(int $ms): \DateTimeImmutable
    {
        return $this->t0->modify("+$ms milliseconds");
    }

    private function local(?TimeControl $tc = null): Game
    {
        return Game::local('g000000000001', self::ALICE, $tc, $this->t0);
    }

    /** @param list<string> $ucis */
    private function playAll(Game $game, array $ucis): void
    {
        foreach ($ucis as $uci) {
            $game->play(self::ALICE, $uci, $this->rules, $this->t0);
        }
    }

    private function clockOf(Game $game): GameClock
    {
        $clock = $game->clock();
        self::assertNotNull($clock);

        return $clock;
    }

    /** @return list<string> */
    private function sans(Game $game): array
    {
        return array_map(static fn(PlayedMove $m): string => $m->san, $game->moves());
    }

    public function testLocalGameStartsPlayingWithBothColoursForTheOwner(): void
    {
        $game = $this->local();

        self::assertSame(GameStatus::Playing, $game->status());
        self::assertSame(GameMode::Local, $game->mode());
        self::assertSame(self::ALICE, $game->whiteId());
        self::assertSame(self::ALICE, $game->blackId());
        self::assertSame([Color::White, Color::Black], $game->colorsOf(self::ALICE));
        self::assertSame([], $game->colorsOf(self::BOB));
        self::assertSame([], $game->colorsOf(null));
        self::assertSame(0, $game->plyCount());
        self::assertSame(['w' => [], 'b' => []], $game->captured());
    }

    public function testUntimedGameHasNoClock(): void
    {
        $game = $this->local();
        $game->play(self::ALICE, 'e2e4', $this->rules, $this->t0);

        self::assertNull($game->clock());
        self::assertNull($game->moves()[0]->whiteMs);
        self::assertNull($game->moves()[0]->blackMs);
    }

    public function testComputerGameOwnerPlaysWhite(): void
    {
        $game = Game::againstComputer('g000000000001', self::ALICE, AiLevel::Beginner, null, $this->t0);

        self::assertSame(GameMode::Computer, $game->mode());
        self::assertSame(AiLevel::Beginner, $game->level());
        self::assertSame([Color::White], $game->colorsOf(self::ALICE));
        self::assertFalse($game->computerToMove());

        $game->play(self::ALICE, 'e2e4', $this->rules, $this->t0);
        self::assertTrue($game->computerToMove());

        $game->playComputer($this->rules->moveFromUci($game->position(), 'e7e5'), $this->rules, $this->at(500));
        self::assertFalse($game->computerToMove());
        self::assertSame(['e4', 'e5'], $this->sans($game));
    }

    public function testScholarsMateFinishesTheGame(): void
    {
        $game = $this->local();
        $this->playAll($game, self::SCHOLARS_MATE);

        self::assertSame(['e4', 'e5', 'Bc4', 'Nc6', 'Qh5', 'Nf6', 'Qxf7#'], $this->sans($game));
        self::assertSame(GameStatus::Finished, $game->status());
        self::assertSame(Result::WhiteWins, $game->result());
        self::assertSame(FinishReason::Checkmate, $game->reason());
        self::assertSame(['w' => ['p'], 'b' => []], $game->captured());
    }

    public function testEnPassantCaptureIsCounted(): void
    {
        $game = $this->local();
        $this->playAll($game, ['e2e4', 'a7a6', 'e4e5', 'd7d5', 'e5d6']);

        self::assertSame(['p'], $game->captured()['w']);
        self::assertSame('exd6', $this->sans($game)[4]);
    }

    public function testThreefoldRepetitionDraws(): void
    {
        $game = $this->local();
        $this->playAll($game, ['g1f3', 'g8f6', 'f3g1', 'f6g8', 'g1f3', 'g8f6', 'f3g1', 'f6g8']);

        self::assertSame(GameStatus::Finished, $game->status());
        self::assertSame(Result::Draw, $game->result());
        self::assertSame(FinishReason::Threefold, $game->reason());
    }

    public function testWrongSideIsConflict(): void
    {
        $game = Game::againstComputer('g000000000001', self::ALICE, AiLevel::Beginner, null, $this->t0);
        $game->play(self::ALICE, 'e2e4', $this->rules, $this->t0);

        $this->expectException(ConflictException::class);
        $this->expectExceptionMessage('Ce n’est pas ton tour.');
        $game->play(self::ALICE, 'e7e5', $this->rules, $this->t0);
    }

    public function testStrangerIsForbidden(): void
    {
        $this->expectException(ForbiddenException::class);
        $this->expectExceptionMessage('Tu ne joues pas cette partie.');
        $this->local()->play(self::BOB, 'e2e4', $this->rules, $this->t0);
    }

    public function testFinishedGameRefusesMoves(): void
    {
        $game = $this->local();
        $this->playAll($game, self::SCHOLARS_MATE);

        $this->expectException(ConflictException::class);
        $this->expectExceptionMessage('La partie n’est pas en cours.');
        $game->play(self::ALICE, 'a7a6', $this->rules, $this->t0);
    }

    public function testComputerCannotPlayOutOfTurn(): void
    {
        $game = Game::againstComputer('g000000000001', self::ALICE, AiLevel::Beginner, null, $this->t0);

        $this->expectException(\LogicException::class);
        $game->playComputer($this->rules->moveFromUci($game->position(), 'e2e4'), $this->rules, $this->t0);
    }

    public function testClockRunsAfterTheFirstLocalMove(): void
    {
        $game = $this->local(new TimeControl(300, 2));
        $game->play(self::ALICE, 'e2e4', $this->rules, $this->t0);

        self::assertSame(Color::Black, $this->clockOf($game)->running);
        self::assertSame(300000, $this->clockOf($game)->whiteMs);

        $game->play(self::ALICE, 'e7e5', $this->rules, $this->at(1500));

        self::assertSame(300500, $this->clockOf($game)->blackMs);
        self::assertSame(Color::White, $this->clockOf($game)->running);
        self::assertSame(300500, $game->moves()[1]->blackMs);
        self::assertEquals($this->at(1500), $game->moves()[1]->playedAt);
        self::assertEquals($this->at(1500), $game->updatedAt());
    }

    public function testMateStopsTheClock(): void
    {
        $game = $this->local(new TimeControl(300, 0));
        $this->playAll($game, self::SCHOLARS_MATE);

        self::assertNull($this->clockOf($game)->running);
    }

    public function testSnapshotRestoreRoundTrip(): void
    {
        $game = $this->local(new TimeControl(300, 2));
        foreach (['e2e4', 'd7d5', 'e4d5', 'd8d5', 'b1c3'] as $i => $uci) {
            $game->play(self::ALICE, $uci, $this->rules, $this->at(1000 * $i));
        }

        $restored = Game::restore($game->snapshot(), $this->rules);

        self::assertSame($game->position()->toFen(), $restored->position()->toFen());
        self::assertEquals($game->moves(), $restored->moves());
        self::assertSame($game->captured(), $restored->captured());
        self::assertEquals($game->clock(), $restored->clock());
        self::assertSame($game->status(), $restored->status());
        self::assertSame($game->plyCount(), $restored->plyCount());
        self::assertEquals($game->snapshot(), $restored->snapshot());
    }

    public function testRestoredGameStillDetectsRepetition(): void
    {
        $game = $this->local();
        $this->playAll($game, ['g1f3', 'g8f6', 'f3g1', 'f6g8', 'g1f3', 'g8f6', 'f3g1']);

        $restored = Game::restore($game->snapshot(), $this->rules);
        $restored->play(self::ALICE, 'f6g8', $this->rules, $this->t0);

        self::assertSame(FinishReason::Threefold, $restored->reason());
    }

    public function testRestoreRejectsAnIllegalStoredMove(): void
    {
        $s = $this->local()->snapshot();
        $corrupt = new GameSnapshot(
            $s->id,
            $s->mode,
            $s->ownerId,
            $s->whiteId,
            $s->blackId,
            $s->level,
            $s->timeControl,
            $s->status,
            [new PlayedMove('e2e5', 'e5', null, null, $this->t0)],
            $s->clock,
            $s->drawOffer,
            $s->result,
            $s->reason,
            $s->createdAt,
            $s->updatedAt,
        );

        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage('corrompue au coup 1');
        Game::restore($corrupt, $this->rules);
    }

    public function testInitialPositionIsTheStandardOne(): void
    {
        self::assertSame(Position::initial()->toFen(), $this->local()->position()->toFen());
    }
}
