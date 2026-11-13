<?php

declare(strict_types=1);

namespace LeCercle\Chess;

/** Résultat d'une partie, au format stocké par le front. */
enum Result: string
{
    case WhiteWins = '1-0';
    case BlackWins = '0-1';
    case Draw = '1/2-1/2';
}
