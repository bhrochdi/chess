<?php

declare(strict_types=1);

namespace LeCercle\Chess;

/** Nature particulière d'un coup. */
enum MoveFlag
{
    case Normal;
    case DoublePush;
    case EnPassant;
    case CastleKingside;
    case CastleQueenside;
}
