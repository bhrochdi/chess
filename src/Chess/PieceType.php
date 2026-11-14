<?php

declare(strict_types=1);

namespace LeCercle\Chess;

/** Type de pièce ; la valeur est la lettre FEN/SAN en majuscule. */
enum PieceType: string
{
    case Pawn = 'P';
    case Knight = 'N';
    case Bishop = 'B';
    case Rook = 'R';
    case Queen = 'Q';
    case King = 'K';
}
