<?php

declare(strict_types=1);

namespace LeCercle\Tests\Unit\Game;

use LeCercle\Chess\Color;
use LeCercle\Game\GameClock;
use LeCercle\Shared\TimeControl;
use PHPUnit\Framework\TestCase;

final class GameClockTest extends TestCase
{
    private \DateTimeImmutable $t0;

    protected function setUp(): void
    {
        $this->t0 = new \DateTimeImmutable('2026-11-12 10:00:00.000', new \DateTimeZone('UTC'));
    }

    private function at(int $ms): \DateTimeImmutable
    {
        return $this->t0->modify("+$ms milliseconds");
    }

    private function started(): GameClock
    {
        return GameClock::start(new TimeControl(300, 2));
    }

    public function testStartGivesFullTimeNotRunning(): void
    {
        $c = $this->started();
        self::assertSame(300000, $c->whiteMs);
        self::assertSame(300000, $c->blackMs);
        self::assertSame(2000, $c->incrementMs);
        self::assertNull($c->running);
        self::assertNull($c->since);
    }

    public function testRemainingCountsDownOnlyForTheRunningSide(): void
    {
        $c = $this->started()->runFor(Color::White, $this->t0);
        self::assertSame(298500, $c->remainingMs(Color::White, $this->at(1500)));
        self::assertSame(300000, $c->remainingMs(Color::Black, $this->at(1500)));
    }

    public function testRemainingNeverNegative(): void
    {
        $c = $this->started()->runFor(Color::White, $this->t0);
        self::assertSame(0, $c->remainingMs(Color::White, $this->at(400000)));
    }

    public function testRemainingIgnoresANowBeforeTheTurnStart(): void
    {
        $c = $this->started()->runFor(Color::White, $this->at(5000));
        self::assertSame(300000, $c->remainingMs(Color::White, $this->t0));
    }

    public function testFirstMoveOfAStoppedClockChargesNothing(): void
    {
        $c = $this->started()->afterMove(Color::White, $this->at(9000));
        self::assertSame(300000, $c->whiteMs);
        self::assertSame(Color::Black, $c->running);
        self::assertEquals($this->at(9000), $c->since);
    }

    public function testMoveChargesElapsedAndAddsIncrement(): void
    {
        $c = $this->started()->runFor(Color::White, $this->t0)->afterMove(Color::White, $this->at(1500));
        self::assertSame(300500, $c->whiteMs);
        self::assertSame(300000, $c->blackMs);
        self::assertSame(Color::Black, $c->running);
    }

    public function testTwoMoves(): void
    {
        $c = $this->started()->runFor(Color::White, $this->t0)
            ->afterMove(Color::White, $this->at(1500))
            ->afterMove(Color::Black, $this->at(4000));
        self::assertSame(299500, $c->blackMs);
        self::assertSame(Color::White, $c->running);
    }

    public function testFlagged(): void
    {
        $c = $this->started()->runFor(Color::White, $this->t0);
        self::assertNull($c->flagged($this->at(299999)));
        self::assertSame(Color::White, $c->flagged($this->at(300000)));
        self::assertNull($this->started()->flagged($this->at(999999)));
    }

    public function testStoppedChargesTheRunningSide(): void
    {
        $c = $this->started()->runFor(Color::White, $this->t0)->stopped($this->at(1000));
        self::assertSame(299000, $c->whiteMs);
        self::assertNull($c->running);
        self::assertNull($c->since);
        self::assertSame(299000, $c->remainingMs(Color::White, $this->at(50000)));
    }

    public function testWithTimes(): void
    {
        $c = $this->started()->withTimes(1000, 2000, Color::Black, $this->t0);
        self::assertSame(1000, $c->whiteMs);
        self::assertSame(2000, $c->blackMs);
        self::assertSame(Color::Black, $c->running);
        self::assertEquals($this->t0, $c->since);
        self::assertSame(2000, $c->incrementMs);
    }
}
