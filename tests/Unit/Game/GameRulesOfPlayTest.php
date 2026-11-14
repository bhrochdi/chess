<?php

declare(strict_types=1);

namespace LeCercle\Tests\Unit\Game;

use LeCercle\Chess\Ai\AiLevel;
use LeCercle\Chess\Color;
use LeCercle\Chess\Result;
use LeCercle\Chess\Square;
use LeCercle\Game\ChessRules;
use LeCercle\Game\FinishReason;
use LeCercle\Game\Game;
use LeCercle\Game\GameClock;
use LeCercle\Game\GameStatus;
use LeCercle\Shared\ConflictException;
use LeCercle\Shared\ForbiddenException;
use LeCercle\Shared\TimeControl;
use PHPUnit\Framework\TestCase;

final class GameRulesOfPlayTest extends TestCase
{
    private const ALICE = 'u000000000001';
    private const BOB = 'u000000000002';
    private const CAROL = 'u000000000003';
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

    private function online(?TimeControl $tc = new TimeControl(300, 0)): Game
    {
        return Game::online('g000000000001', self::ALICE, Color::White, $tc, $this->t0);
    }

    /** Partie en ligne alice (blancs) contre bob (noirs), commencée à $t0. */
    private function onlinePlaying(?TimeControl $tc = new TimeControl(300, 0)): Game
    {
        $game = $this->online($tc);
        $game->join(self::BOB, $this->t0);

        return $game;
    }

    private function local(?TimeControl $tc = null): Game
    {
        return Game::local('g000000000001', self::ALICE, $tc, $this->t0);
    }

    private function computer(?TimeControl $tc = null): Game
    {
        return Game::againstComputer('g000000000001', self::ALICE, AiLevel::Beginner, $tc, $this->t0);
    }

    /** @param list<string> $ucis */
    private function playAll(Game $game, array $ucis): void
    {
        foreach ($ucis as $uci) {
            $game->play(self::ALICE, $uci, $this->rules, $this->t0);
        }
    }

    private function computerPlays(Game $game, string $uci): void
    {
        $game->playComputer($this->rules->moveFromUci($game->position(), $uci), $this->rules, $this->t0);
    }

    private function clockOf(Game $game): GameClock
    {
        $clock = $game->clock();
        self::assertNotNull($clock);

        return $clock;
    }

    public function testOnlineGameWaitsForAnOpponent(): void
    {
        $game = $this->online();

        self::assertSame(GameStatus::Open, $game->status());
        self::assertSame(self::ALICE, $game->whiteId());
        self::assertNull($game->blackId());
        self::assertSame([Color::White], $game->colorsOf(self::ALICE));
        self::assertNull($this->clockOf($game)->running);
    }

    public function testCreatorMayTakeBlack(): void
    {
        $game = Game::online('g000000000001', self::ALICE, Color::Black, null, $this->t0);

        self::assertNull($game->whiteId());
        self::assertSame(self::ALICE, $game->blackId());
        self::assertSame(self::ALICE, $game->ownerId());

        $game->join(self::BOB, $this->t0);
        self::assertSame(self::BOB, $game->whiteId());
        self::assertNull($game->clock());
    }

    public function testJoinStartsTheGameAndWhitesClock(): void
    {
        $game = $this->online();
        $game->join(self::BOB, $this->at(1000));

        self::assertSame(GameStatus::Playing, $game->status());
        self::assertSame(self::BOB, $game->blackId());
        self::assertSame(Color::White, $this->clockOf($game)->running);
        self::assertEquals($this->at(1000), $this->clockOf($game)->since);
        self::assertEquals($this->at(1000), $game->updatedAt());
    }

    public function testJoinByAPlayerIsANoOp(): void
    {
        $game = $this->online();
        $game->join(self::ALICE, $this->at(1000));

        self::assertSame(GameStatus::Open, $game->status());
        self::assertNull($game->blackId());
        self::assertEquals($this->t0, $game->updatedAt());
    }

    public function testJoinOfAStartedGameIsConflict(): void
    {
        $game = $this->onlinePlaying();

        $this->expectException(ConflictException::class);
        $this->expectExceptionMessage('Cette partie a déjà commencé.');
        $game->join(self::CAROL, $this->t0);
    }

    public function testJoinByAPlayerOfAStartedGameIsANoOp(): void
    {
        $game = $this->onlinePlaying();
        $game->join(self::BOB, $this->at(5000));

        self::assertEquals($this->t0, $this->clockOf($game)->since);
    }

    public function testMoveBeforeTheOpponentJoinsIsConflict(): void
    {
        $this->expectException(ConflictException::class);
        $this->expectExceptionMessage('La partie n’est pas en cours.');
        $this->online()->play(self::ALICE, 'e2e4', $this->rules, $this->t0);
    }

    public function testOnlineMovesAlternateBetweenThePlayers(): void
    {
        $game = $this->onlinePlaying();
        $game->play(self::ALICE, 'e2e4', $this->rules, $this->at(1000));

        self::assertSame(299000, $this->clockOf($game)->whiteMs);

        $this->expectException(ConflictException::class);
        $this->expectExceptionMessage('Ce n’est pas ton tour.');
        $game->play(self::ALICE, 'e7e5', $this->rules, $this->at(2000));
    }

    public function testResign(): void
    {
        $game = $this->onlinePlaying();
        $game->resign(self::BOB, $this->at(3000));

        self::assertSame(GameStatus::Finished, $game->status());
        self::assertSame(Result::WhiteWins, $game->result());
        self::assertSame(FinishReason::Resignation, $game->reason());
        self::assertNull($this->clockOf($game)->running);
        self::assertSame(297000, $this->clockOf($game)->whiteMs);
    }

    public function testResignByWhiteGivesBlackTheWin(): void
    {
        $game = $this->onlinePlaying();
        $game->resign(self::ALICE, $this->t0);

        self::assertSame(Result::BlackWins, $game->result());
    }

    public function testResignByAStrangerIsForbidden(): void
    {
        $this->expectException(ForbiddenException::class);
        $this->expectExceptionMessage('Tu ne joues pas cette partie.');
        $this->onlinePlaying()->resign(self::CAROL, $this->t0);
    }

    public function testResignInALocalGameIsConflict(): void
    {
        $this->expectException(ConflictException::class);
        $this->expectExceptionMessage('Abandon réservé aux parties en ligne.');
        $this->local()->resign(self::ALICE, $this->t0);
    }

    public function testResignOfAFinishedGameIsConflict(): void
    {
        $game = $this->onlinePlaying();
        $game->resign(self::BOB, $this->t0);

        $this->expectException(ConflictException::class);
        $this->expectExceptionMessage('La partie n’est pas en cours.');
        $game->resign(self::ALICE, $this->t0);
    }

    public function testDrawOfferAndAcceptance(): void
    {
        $game = $this->onlinePlaying();
        $game->offerDraw(self::ALICE, $this->at(1000));

        self::assertSame(Color::White, $game->drawOffer());

        try {
            $game->acceptDraw(self::ALICE, $this->at(2000));
            self::fail('Accepter sa propre proposition doit être refusé.');
        } catch (ConflictException $e) {
            self::assertSame('Aucune proposition de nulle à accepter.', $e->getMessage());
        }

        $game->acceptDraw(self::BOB, $this->at(2000));

        self::assertSame(GameStatus::Finished, $game->status());
        self::assertSame(Result::Draw, $game->result());
        self::assertSame(FinishReason::Agreement, $game->reason());
        self::assertNull($game->drawOffer());
    }

    public function testAcceptDrawWithoutOfferIsConflict(): void
    {
        $this->expectException(ConflictException::class);
        $this->expectExceptionMessage('Aucune proposition de nulle à accepter.');
        $this->onlinePlaying()->acceptDraw(self::BOB, $this->t0);
    }

    public function testDrawRefusals(): void
    {
        $refusals = [
            'local, offre' => [fn() => $this->local()->offerDraw(self::ALICE, $this->t0), ConflictException::class, 'Nulle par accord réservée aux parties en ligne.'],
            'local, acceptation' => [fn() => $this->local()->acceptDraw(self::ALICE, $this->t0), ConflictException::class, 'Nulle par accord réservée aux parties en ligne.'],
            'ouverte, offre' => [fn() => $this->online()->offerDraw(self::ALICE, $this->t0), ConflictException::class, 'La partie n’est pas en cours.'],
            'ouverte, acceptation' => [fn() => $this->online()->acceptDraw(self::ALICE, $this->t0), ConflictException::class, 'La partie n’est pas en cours.'],
            'étrangère, offre' => [fn() => $this->onlinePlaying()->offerDraw(self::CAROL, $this->t0), ForbiddenException::class, 'Tu ne joues pas cette partie.'],
            'étrangère, acceptation' => [fn() => $this->onlinePlaying()->acceptDraw(self::CAROL, $this->t0), ForbiddenException::class, 'Tu ne joues pas cette partie.'],
        ];
        foreach ($refusals as $case => [$call, $class, $message]) {
            try {
                $call();
                self::fail("$case : exception attendue.");
            } catch (ConflictException|ForbiddenException $e) {
                self::assertInstanceOf($class, $e, $case);
                self::assertSame($message, $e->getMessage(), $case);
            }
        }
    }

    public function testAMoveClearsTheDrawOffer(): void
    {
        $game = $this->onlinePlaying();
        $game->offerDraw(self::ALICE, $this->t0);
        $game->play(self::ALICE, 'e2e4', $this->rules, $this->t0);

        self::assertNull($game->drawOffer());
    }

    public function testFlagFallFinishesOnTime(): void
    {
        $game = $this->onlinePlaying(new TimeControl(60, 0));

        self::assertFalse($game->checkFlag($this->at(59999)));
        self::assertSame(GameStatus::Playing, $game->status());

        self::assertTrue($game->checkFlag($this->at(60000)));
        self::assertSame(GameStatus::Finished, $game->status());
        self::assertSame(Result::BlackWins, $game->result());
        self::assertSame(FinishReason::Time, $game->reason());
        self::assertNull($this->clockOf($game)->running);
        self::assertSame(0, $this->clockOf($game)->whiteMs);
        self::assertEquals($this->at(60000), $game->updatedAt());

        self::assertFalse($game->checkFlag($this->at(70000)));
    }

    public function testCheckFlagWithoutRunningClockDoesNothing(): void
    {
        self::assertFalse($this->onlinePlaying(null)->checkFlag($this->at(1_000_000)));
        self::assertFalse($this->online(new TimeControl(60, 0))->checkFlag($this->at(1_000_000)));
        self::assertFalse($this->local(new TimeControl(60, 0))->checkFlag($this->at(1_000_000)));
    }

    public function testUndoInALocalGame(): void
    {
        $game = $this->local(new TimeControl(300, 2));
        $game->play(self::ALICE, 'e2e4', $this->rules, $this->t0);
        $game->play(self::ALICE, 'e7e5', $this->rules, $this->at(1500));
        $afterTwo = $game->position()->toFen();
        $game->play(self::ALICE, 'g1f3', $this->rules, $this->at(4000));

        $game->undo(self::ALICE, $this->rules, $this->at(9000));

        self::assertSame(2, $game->plyCount());
        self::assertSame($afterTwo, $game->position()->toFen());
        $clock = $this->clockOf($game);
        self::assertSame($game->moves()[1]->whiteMs, $clock->whiteMs);
        self::assertSame($game->moves()[1]->blackMs, $clock->blackMs);
        self::assertSame(2000, $clock->incrementMs);
        self::assertSame(Color::White, $clock->running);
        self::assertEquals($this->at(9000), $clock->since);
        self::assertEquals($this->at(9000), $game->updatedAt());
    }

    public function testUndoAfterMateReopensTheGame(): void
    {
        $game = $this->local(new TimeControl(300, 0));
        $this->playAll($game, self::SCHOLARS_MATE);

        $game->undo(self::ALICE, $this->rules, $this->t0);

        self::assertSame(GameStatus::Playing, $game->status());
        self::assertNull($game->result());
        self::assertNull($game->reason());
        self::assertSame([], $game->captured()['w']);
        self::assertSame(Color::White, $this->clockOf($game)->running);
    }

    public function testUndoKeepsTheRepetitionHistory(): void
    {
        $game = $this->local();
        $this->playAll($game, ['g1f3', 'g8f6', 'f3g1', 'f6g8', 'g1f3', 'g8f6', 'f3g1', 'b8c6']);

        $game->undo(self::ALICE, $this->rules, $this->t0);
        $game->play(self::ALICE, 'f6g8', $this->rules, $this->t0);

        self::assertSame(FinishReason::Threefold, $game->reason());
    }

    public function testUndoInAComputerGameGoesBackToTheOwnersTurn(): void
    {
        $game = $this->computer(new TimeControl(300, 0));
        $game->play(self::ALICE, 'e2e4', $this->rules, $this->t0);
        $this->computerPlays($game, 'e7e5');

        $game->undo(self::ALICE, $this->rules, $this->at(1000));

        self::assertSame(0, $game->plyCount());
        $clock = $this->clockOf($game);
        self::assertNull($clock->running);
        self::assertNull($clock->since);
        self::assertSame(300000, $clock->whiteMs);
        self::assertSame(300000, $clock->blackMs);
    }

    public function testUndoAfterTheOwnerMatesRemovesOnePly(): void
    {
        $game = $this->computer();
        foreach (self::SCHOLARS_MATE as $i => $uci) {
            if ($i % 2 === 0) {
                $game->play(self::ALICE, $uci, $this->rules, $this->t0);
            } else {
                $this->computerPlays($game, $uci);
            }
        }
        self::assertSame(GameStatus::Finished, $game->status());

        $game->undo(self::ALICE, $this->rules, $this->t0);

        self::assertSame(6, $game->plyCount());
        self::assertSame(Color::White, $game->position()->sideToMove());
        self::assertSame(GameStatus::Playing, $game->status());
        self::assertFalse($game->computerToMove());
    }

    public function testUndoRefusals(): void
    {
        $online = $this->onlinePlaying();
        $online->play(self::ALICE, 'e2e4', $this->rules, $this->t0);
        $played = $this->local();
        $played->play(self::ALICE, 'e2e4', $this->rules, $this->t0);

        $refusals = [
            'en ligne' => [fn() => $online->undo(self::ALICE, $this->rules, $this->t0), ConflictException::class, 'Annulation réservée aux parties locales.'],
            'aucun coup' => [fn() => $this->local()->undo(self::ALICE, $this->rules, $this->t0), ConflictException::class, 'Aucun coup à annuler.'],
            'étranger' => [fn() => $played->undo(self::BOB, $this->rules, $this->t0), ForbiddenException::class, 'Tu ne joues pas cette partie.'],
        ];
        foreach ($refusals as $case => [$call, $class, $message]) {
            try {
                $call();
                self::fail("$case : exception attendue.");
            } catch (ConflictException|ForbiddenException $e) {
                self::assertInstanceOf($class, $e, $case);
                self::assertSame($message, $e->getMessage(), $case);
            }
        }
        self::assertSame(1, $played->plyCount());
    }

    public function testPromotionIsWrittenWithTheNewPiece(): void
    {
        $game = $this->local();
        $this->playAll($game, ['a2a4', 'b7b5', 'a4b5', 'a7a6', 'b5a6', 'b8c6', 'a6a7', 'a8b8', 'a7a8q']);

        self::assertSame('a8=Q', $game->moves()[8]->san);
        self::assertSame('Q', $game->position()->pieceAt(Square::fromAlgebraic('a8'))?->toFenChar());
        self::assertSame(['p', 'p'], $game->captured()['w']);
    }

    public function testCapturePromotionCountsOnlyTheCapturedPiece(): void
    {
        $game = $this->local();
        $this->playAll($game, ['a2a4', 'b7b5', 'a4b5', 'a7a6', 'b5a6', 'b8c6', 'a6a7', 'a8b8', 'a7b8q']);

        self::assertSame('axb8=Q', $game->moves()[8]->san);
        self::assertSame(['p', 'p', 'r'], $game->captured()['w']);
        self::assertSame([], $game->captured()['b']);
    }
}
