<?php

declare(strict_types=1);

namespace LeCercle\Game;

use LeCercle\Chess\Result;

/** Prévenu dans la transaction de l'écriture qui termine une partie (report vers un tournoi, par exemple). */
interface GameEndListener
{
    public function gameFinished(string $gameId, Result $result): void;
}
