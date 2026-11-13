<?php

declare(strict_types=1);

namespace LeCercle\Tests\Support;

use LeCercle\Chess\Ai\AiLevel;
use LeCercle\Chess\Ai\MoveChooser;
use LeCercle\Chess\Move;
use LeCercle\Chess\Position;
use LeCercle\Game\ChessRules;
use LeCercle\Game\ComputerPlayers;

/** Double de test : l'ordinateur joue les coups UCI du script, puis le premier coup légal. */
final class ScriptedComputerPlayers implements ComputerPlayers
{
    /** @var list<AiLevel> niveaux demandés, dans l'ordre */
    public array $levelsAsked = [];

    /** @param list<string> $ucis */
    public function __construct(
        private readonly ChessRules $rules,
        private array $ucis = [],
        private readonly ?\Closure $beforeAnswer = null,
    ) {}

    public function chooserFor(AiLevel $level): MoveChooser
    {
        $this->levelsAsked[] = $level;

        return new class ($this) implements MoveChooser {
            public function __construct(private readonly ScriptedComputerPlayers $players) {}

            public function choose(Position $p): ?Move
            {
                return $this->players->answer($p);
            }
        };
    }

    /** Coup suivant du script, ou le premier coup légal ; le crochet $beforeAnswer passe avant. */
    public function answer(Position $p): ?Move
    {
        if ($this->beforeAnswer !== null) {
            ($this->beforeAnswer)();
        }
        $uci = array_shift($this->ucis);
        if ($uci !== null) {
            return $this->rules->moveFromUci($p, $uci);
        }

        return $this->rules->generator()->legalMoves($p)[0] ?? null;
    }
}
