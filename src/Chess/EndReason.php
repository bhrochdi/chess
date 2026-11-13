<?php

declare(strict_types=1);

namespace LeCercle\Chess;

/** Motif de fin de partie, aux valeurs déjà stockées par le front. */
enum EndReason: string
{
    case Checkmate = 'mat';
    case Stalemate = 'pat';
    case FiftyMoves = '50coups';
    case Threefold = 'repetition';
    case InsufficientMaterial = 'materiel';
}
