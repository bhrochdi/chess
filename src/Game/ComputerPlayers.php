<?php

declare(strict_types=1);

namespace LeCercle\Game;

use LeCercle\Chess\Ai\AiLevel;
use LeCercle\Chess\Ai\MoveChooser;

/** Fournit l'adversaire ordinateur d'un niveau donné. */
interface ComputerPlayers
{
    public function chooserFor(AiLevel $level): MoveChooser;
}
