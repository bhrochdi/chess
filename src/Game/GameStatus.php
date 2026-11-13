<?php

declare(strict_types=1);

namespace LeCercle\Game;

/** Statut d'une partie : ouverte, en cours ou terminée. */
enum GameStatus: string
{
    case Open = 'open';
    case Playing = 'playing';
    case Finished = 'finished';
}
