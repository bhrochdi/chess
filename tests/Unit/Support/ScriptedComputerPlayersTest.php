<?php

declare(strict_types=1);

namespace LeCercle\Tests\Unit\Support;

use LeCercle\Chess\Ai\AiLevel;
use LeCercle\Chess\Position;
use LeCercle\Game\ChessRules;
use LeCercle\Tests\Support\ScriptedComputerPlayers;
use PHPUnit\Framework\TestCase;

final class ScriptedComputerPlayersTest extends TestCase
{
    public function testPlaysTheScriptInOrderAcrossChoosers(): void
    {
        $rules = ChessRules::standard();
        $computer = new ScriptedComputerPlayers($rules, ['e2e4', 'd2d4']);
        $initial = Position::initial();

        self::assertSame('e2e4', $computer->chooserFor(AiLevel::Beginner)->choose($initial)?->toUci());
        self::assertSame('d2d4', $computer->chooserFor(AiLevel::Master)->choose($initial)?->toUci());
        self::assertSame([AiLevel::Beginner, AiLevel::Master], $computer->levelsAsked);
    }

    public function testEmptyScriptPlaysTheFirstLegalMove(): void
    {
        $rules = ChessRules::standard();
        $computer = new ScriptedComputerPlayers($rules);

        self::assertSame(
            $rules->legalUcis(Position::initial())[0],
            $computer->chooserFor(AiLevel::Club)->choose(Position::initial())?->toUci(),
        );
    }

    public function testRunsTheHookBeforeEachAnswer(): void
    {
        $calls = 0;
        $computer = new ScriptedComputerPlayers(ChessRules::standard(), [], function () use (&$calls): void {
            ++$calls;
        });
        $chooser = $computer->chooserFor(AiLevel::Club);

        $chooser->choose(Position::initial());
        $chooser->choose(Position::initial());

        self::assertSame(2, $calls);
    }

    public function testAnIllegalScriptedMoveIsRefused(): void
    {
        $computer = new ScriptedComputerPlayers(ChessRules::standard(), ['e2e5']);

        $this->expectException(\LeCercle\Shared\ValidationException::class);
        $computer->chooserFor(AiLevel::Club)->choose(Position::initial());
    }
}
