<?php

declare(strict_types=1);

namespace LeCercle\Game;

use LeCercle\Chess\EndReason;

/** Motif de fin d'une partie, aux valeurs déjà stockées par le front. */
enum FinishReason: string
{
    case Checkmate = 'mat';
    case Stalemate = 'pat';
    case FiftyMoves = '50coups';
    case Threefold = 'repetition';
    case InsufficientMaterial = 'materiel';
    case Resignation = 'abandon';
    case Agreement = 'accord';
    case Time = 'temps';
    case Admin = 'admin';

    /** Convertit un motif de fin du moteur (même valeur) en motif de partie. */
    public static function fromEndReason(EndReason $reason): self
    {
        return self::from($reason->value);
    }
}
