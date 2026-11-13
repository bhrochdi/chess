<?php

declare(strict_types=1);

namespace LeCercle\Game;

use LeCercle\Chess\Ai\AiLevel;
use LeCercle\Chess\Ai\MoveChooser;
use LeCercle\Chess\Ai\RandomSource;
use LeCercle\Chess\MoveGenerator;

/** L'ordinateur de production : le minimax du domaine échecs, réglé par le niveau. */
final class MinimaxComputerPlayers implements ComputerPlayers
{
    public function __construct(private readonly MoveGenerator $moves, private readonly RandomSource $random) {}

    public function chooserFor(AiLevel $level): MoveChooser
    {
        return $level->chooser($this->moves, $this->random);
    }
}
