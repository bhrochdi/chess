<?php

declare(strict_types=1);

namespace LeCercle\Tests\Unit\Game;

use LeCercle\Chess\Color;
use LeCercle\Game\GameClock;
use LeCercle\Game\GameMode;
use LeCercle\Game\GamePulse;
use LeCercle\Game\GameStatus;
use LeCercle\Shared\Millis;
use PHPUnit\Framework\TestCase;

final class GamePulseTest extends TestCase
{
    private const T = 1_794_477_600_000;

    private function pulse(
        GameStatus $status = GameStatus::Playing,
        int $plyCount = 3,
        ?Color $drawOffer = null,
        ?GameClock $clock = null,
        int $updatedAt = self::T,
    ): GamePulse {
        return new GamePulse(GameMode::Online, 'alice', $status, $plyCount, $drawOffer, $clock, Millis::toDateTime($updatedAt));
    }

    public function testVersionCarriesInstantPlyStatusAndDrawOffer(): void
    {
        self::assertSame('1794477600000.3.playing.-', $this->pulse()->version());
        self::assertSame('1794477600000.3.playing.w', $this->pulse(drawOffer: Color::White)->version());
    }

    public function testVersionChangesWithEachPart(): void
    {
        $versions = [
            $this->pulse()->version(),
            $this->pulse(updatedAt: self::T + 1)->version(),
            $this->pulse(plyCount: 4)->version(),
            $this->pulse(status: GameStatus::Finished)->version(),
            $this->pulse(drawOffer: Color::Black)->version(),
        ];

        self::assertCount(5, array_unique($versions));
    }

    public function testFlagFallenOnlyWhenTheRunningSideIsOutOfTime(): void
    {
        $since = Millis::toDateTime(self::T);
        $running = new GameClock(5000, 60_000, 0, Color::White, $since);

        self::assertFalse($this->pulse(clock: $running)->flagFallen(Millis::toDateTime(self::T + 4999)));
        self::assertTrue($this->pulse(clock: $running)->flagFallen(Millis::toDateTime(self::T + 5000)));
    }

    public function testNoFlagWithoutARunningClockOrOnceFinished(): void
    {
        $since = Millis::toDateTime(self::T);
        $late = Millis::toDateTime(self::T + 600_000);

        self::assertFalse($this->pulse()->flagFallen($late));
        self::assertFalse($this->pulse(clock: new GameClock(5000, 5000, 0, null, null))->flagFallen($late));
        self::assertFalse(
            $this->pulse(status: GameStatus::Finished, clock: new GameClock(5000, 5000, 0, Color::White, $since))->flagFallen($late),
        );
    }
}
