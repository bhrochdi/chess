<?php

declare(strict_types=1);

namespace LeCercle\Tests\Unit\Chess;

use LeCercle\Chess\MoveGenerator;
use LeCercle\Chess\Position;

/** Compte les feuilles de l'arbre des coups légaux (perft) pour valider le générateur. */
final class Perft
{
    public static function count(MoveGenerator $g, Position $p, int $depth): int
    {
        if ($depth === 0) {
            return 1;
        }
        $moves = $g->legalMoves($p);
        if ($depth === 1) {
            return count($moves);
        }
        $nodes = 0;
        foreach ($moves as $m) {
            $nodes += self::count($g, $p->apply($m), $depth - 1);
        }

        return $nodes;
    }
}
