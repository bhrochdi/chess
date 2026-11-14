<?php

declare(strict_types=1);

namespace LeCercle\Tests\Unit\Tournament;

use LeCercle\Chess\Result;
use LeCercle\Tournament\Pairing;
use PHPUnit\Framework\TestCase;

final class PairingTest extends TestCase
{
    public function testByeIsWonByThePresentPlayer(): void
    {
        $p = Pairing::bye(3, 'A');

        self::assertTrue($p->isBye());
        self::assertSame(Result::WhiteWins, $p->result);
        self::assertSame('A', $p->winner());
        self::assertSame(3, $p->board);
    }

    public function testWinnerFollowsTheResult(): void
    {
        self::assertSame('B', (new Pairing(1, 'A', 'B', Result::BlackWins))->winner());
        self::assertSame('A', (new Pairing(1, 'A', 'B', Result::WhiteWins))->winner());
        self::assertNull((new Pairing(1, 'A', 'B', Result::Draw))->winner());
        self::assertNull((new Pairing(1, 'A', 'B'))->winner());
    }

    public function testLoneBlackIsTheWinner(): void
    {
        $p = new Pairing(1, null, 'B');

        self::assertTrue($p->isBye());
        self::assertSame('B', $p->winner());
    }

    public function testWithResultClearsAndSets(): void
    {
        $p = new Pairing(1, 'A', 'B', Result::Draw, 'g1');

        $cleared = $p->withResult(null);
        self::assertNull($cleared->result);
        self::assertSame('g1', $cleared->gameId);
        self::assertSame(Result::WhiteWins, $cleared->withResult(Result::WhiteWins)->result);
        self::assertSame(Result::Draw, $p->result);
    }

    public function testInvolves(): void
    {
        $p = new Pairing(1, 'A', 'B');

        self::assertTrue($p->involves('A'));
        self::assertTrue($p->involves('B'));
        self::assertFalse($p->involves('C'));
    }
}
