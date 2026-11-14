<?php

declare(strict_types=1);

namespace LeCercle\Tournament;

use LeCercle\Chess\Result;

/** Classement d'un tournoi : port de calculerClassement (barème 1 / ½ / 0). */
final class Standings
{
    /**
     * @param list<string>          $players
     * @param list<Round>           $rounds
     * @param array<string, string> $pseudos id => pseudo
     * @return list<StandingRow>
     */
    public function compute(array $players, array $rounds, array $pseudos): array
    {
        $half = [];
        $played = [];
        $wins = [];
        foreach ($players as $id) {
            $half[$id] = 0;
            $played[$id] = 0;
            $wins[$id] = 0;
        }

        foreach ($rounds as $round) {
            foreach ($round->pairings as $p) {
                if ($p->result === null) {
                    continue;
                }
                if ($p->isBye()) {
                    $id = $p->whiteId ?? $p->blackId;
                    if ($id !== null && isset($half[$id])) {
                        $half[$id] += 2;
                        $played[$id]++;
                        $wins[$id]++;
                    }
                    continue;
                }
                $w = $p->whiteId;
                $b = $p->blackId;
                // Une rencontre avec un joueur inconnu est écartée en entier, comme en JS.
                if ($w === null || $b === null || !isset($half[$w], $half[$b])) {
                    continue;
                }
                $played[$w]++;
                $played[$b]++;
                if ($p->result === Result::WhiteWins) {
                    $half[$w] += 2;
                    $wins[$w]++;
                } elseif ($p->result === Result::BlackWins) {
                    $half[$b] += 2;
                    $wins[$b]++;
                } else {
                    $half[$w]++;
                    $half[$b]++;
                }
            }
        }

        $rows = [];
        foreach (array_keys($half) as $id) {
            $rows[] = new StandingRow((string) $id, $half[$id], $played[$id], $wins[$id]);
        }

        // Départage par pseudo : strcmp(mb_strtolower()) au lieu de localeCompare côté JS ;
        // l'ordre peut différer pour des pseudos accentués (écart accepté).
        $name = static fn(StandingRow $r): string => mb_strtolower($pseudos[$r->playerId] ?? $r->playerId);
        usort($rows, static fn(StandingRow $a, StandingRow $b): int => $b->halfPoints <=> $a->halfPoints
            ?: $b->wins <=> $a->wins
            ?: strcmp($name($a), $name($b))
            ?: strcmp($a->playerId, $b->playerId));

        return $rows;
    }
}
