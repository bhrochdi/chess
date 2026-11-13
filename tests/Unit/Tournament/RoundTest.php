<?php

declare(strict_types=1);

namespace LeCercle\Tests\Unit\Tournament;

use LeCercle\Chess\Result;
use LeCercle\Tournament\Pairing;
use LeCercle\Tournament\Round;
use PHPUnit\Framework\TestCase;

final class RoundTest extends TestCase
{
    public function testIsComplete(): void
    {
        self::assertTrue((new Round(1, [new Pairing(1, 'A', 'B', Result::WhiteWins), Pairing::bye(2, 'C')]))->isComplete());
        self::assertFalse((new Round(1, [new Pairing(1, 'A', 'B')]))->isComplete());
    }

    public function testPairingLookup(): void
    {
        $round = new Round(1, [new Pairing(1, 'A', 'B')]);

        self::assertSame('A', $round->pairing(1)?->whiteId);
        self::assertNull($round->pairing(2));
    }

    public function testWithPairingReplacesOnlyThatBoard(): void
    {
        $round = new Round(2, [new Pairing(1, 'A', 'B'), new Pairing(2, 'C', 'D')]);

        $next = $round->withPairing(new Pairing(1, 'A', 'B', Result::Draw));

        self::assertSame(2, $next->number);
        self::assertSame(Result::Draw, $next->pairing(1)?->result);
        self::assertNull($next->pairing(2)?->result);
        self::assertNull($round->pairing(1)?->result);
        self::assertCount(2, $next->pairings);
    }
}
