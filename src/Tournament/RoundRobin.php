<?php

declare(strict_types=1);

namespace LeCercle\Tournament;

/** Appariement toutes rondes par la méthode du cercle (port de genererRoundRobin). */
final class RoundRobin
{
    /**
     * @param list<string> $players
     * @return list<Round>
     */
    public function rounds(array $players): array
    {
        if (count($players) < 2) {
            throw new \InvalidArgumentException('Il faut au moins deux joueurs.');
        }

        $arr = $this->withBye($players);
        $n = count($arr);
        $rounds = [];
        for ($r = 0; $r < $n - 1; ++$r) {
            $pairings = [];
            $board = 1;
            for ($i = 0; $i < intdiv($n, 2); ++$i) {
                $a = $arr[$i];
                $b = $arr[$n - 1 - $i];
                if ($a === null || $b === null) {
                    continue; // l'exempt ne joue pas
                }
                $pairings[] = $r % 2 === 0
                    ? new Pairing($board, $a, $b)
                    : new Pairing($board, $b, $a);
                ++$board;
            }
            $rounds[] = new Round($r + 1, $pairings);
            // rotation : l'index 0 reste fixe, le dernier passe en index 1
            $last = array_pop($arr);
            array_splice($arr, 1, 0, [$last]);
        }

        return $rounds;
    }

    /**
     * Ajoute un joueur exempt (null) si l'effectif est impair.
     *
     * @param list<string> $players
     * @return list<?string>
     */
    private function withBye(array $players): array
    {
        /** @var list<?string> $arr */
        $arr = $players;
        if (count($arr) % 2 === 1) {
            $arr[] = null;
        }

        return $arr;
    }
}
