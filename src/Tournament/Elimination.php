<?php

declare(strict_types=1);

namespace LeCercle\Tournament;

use LeCercle\Chess\Result;
use LeCercle\Shared\ConflictException;

/** Élimination directe avec têtes de série et exempts (port de genererElimination). */
final class Elimination
{
    /**
     * Port de ordreBracket : indice de tête de série (base 0) de chaque emplacement.
     *
     * @return list<int>
     */
    public static function bracketOrder(int $size): array
    {
        $a = [1];
        while (count($a) < $size) {
            $m = count($a) * 2 + 1;
            $b = [];
            foreach ($a as $x) {
                $b[] = $x;
                $b[] = $m - $x;
            }
            $a = $b;
        }

        return array_map(static fn(int $x): int => $x - 1, $a);
    }

    /**
     * Les exempts complètent jusqu'à la puissance de 2 supérieure ; un exempt
     * donne une victoire immédiate.
     *
     * @param list<string> $players têtes de série dans l'ordre d'inscription
     */
    public function firstRound(array $players): Round
    {
        if (count($players) < 2) {
            throw new \InvalidArgumentException('Il faut au moins deux joueurs.');
        }

        $size = 1;
        while ($size < count($players)) {
            $size *= 2;
        }
        $seeds = array_pad($players, $size, null);

        $order = self::bracketOrder($size);
        $pairings = [];
        for ($i = 0; $i < $size; $i += 2) {
            $white = $seeds[$order[$i]];
            $black = $seeds[$order[$i + 1]];
            $board = intdiv($i, 2) + 1;
            if ($white !== null && $black === null) {
                $pairings[] = Pairing::bye($board, $white);
            } elseif ($white === null && $black !== null) {
                $pairings[] = new Pairing($board, null, $black, Result::BlackWins);
            } else {
                $pairings[] = new Pairing($board, $white, $black);
            }
        }

        return new Round(1, $pairings);
    }

    /** @return Round|null null quand la ronde courante est la finale */
    public function nextRound(Round $current): ?Round
    {
        $winners = [];
        foreach ($current->pairings as $p) {
            $winner = $p->winner();
            if ($winner === null) {
                throw new ConflictException('Élimination : un match est nul. Saisis un résultat décisif (départage) avant de continuer.');
            }
            $winners[] = $winner;
        }
        if (count($winners) === 1) {
            return null;
        }

        $pairings = [];
        for ($i = 0; $i < count($winners); $i += 2) {
            $pairings[] = new Pairing(intdiv($i, 2) + 1, $winners[$i], $winners[$i + 1]);
        }

        return new Round($current->number + 1, $pairings);
    }
}
