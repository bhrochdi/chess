<?php

declare(strict_types=1);

namespace LeCercle\Game;

/** Mode d'une partie : locale, contre l'ordinateur ou en ligne. */
enum GameMode: string
{
    case Local = 'local';
    case Computer = 'ai';
    case Online = 'online';
}
