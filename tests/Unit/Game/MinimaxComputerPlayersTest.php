<?php

declare(strict_types=1);

namespace LeCercle\Tests\Unit\Game;

use LeCercle\Chess\Ai\AiLevel;
use LeCercle\Chess\Position;
use LeCercle\Game\ChessRules;
use LeCercle\Game\MinimaxComputerPlayers;
use LeCercle\Tests\Unit\Chess\Ai\FixedRandom;
use PHPUnit\Framework\TestCase;

final class MinimaxComputerPlayersTest extends TestCase
{
    public function testBeginnerChoosesALegalMove(): void
    {
        $rules = ChessRules::standard();
        $computer = new MinimaxComputerPlayers($rules->generator(), new FixedRandom(0.0));

        $move = $computer->chooserFor(AiLevel::Beginner)->choose(Position::initial());

        self::assertNotNull($move);
        self::assertContains($move->toUci(), $rules->legalUcis(Position::initial()));
    }
}
