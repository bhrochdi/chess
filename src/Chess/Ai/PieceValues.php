<?php

declare(strict_types=1);

namespace LeCercle\Chess\Ai;

use LeCercle\Chess\PieceType;

/** @internal Valeur des pièces en centipions (`VALUE_AI` de l'ancien js/engine.js), pour l'évaluation et l'ordre des captures. */
final class PieceValues
{
    private const VALUES = [
        'P' => 100,
        'N' => 320,
        'B' => 330,
        'R' => 500,
        'Q' => 900,
        'K' => 20000,
    ];

    public static function of(PieceType $type): int
    {
        return self::VALUES[$type->value];
    }
}
