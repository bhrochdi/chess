<?php

declare(strict_types=1);

namespace LeCercle\Tournament;

/** Appariement suisse par score, sans revanche (port de apparierSuisse). */
final class Swiss
{
    /**
     * L'ordre des joueurs est celui du classement ; avec un nombre impair, le bye
     * (un point) va au joueur le moins bien classé qui n'en a pas encore eu.
     *
     * @param list<Round>       $rounds
     * @param list<StandingRow> $standings
     */
    public function nextRound(array $rounds, array $standings): Round
    {
        $order = array_map(static fn(StandingRow $r): string => $r->playerId, $standings);

        $met = [];
        $hadBye = [];
        $last = 0;
        foreach ($rounds as $round) {
            $last = $round->number;
            foreach ($round->pairings as $p) {
                if ($p->whiteId !== null && $p->blackId !== null) {
                    $met[$p->whiteId . '|' . $p->blackId] = true;
                    $met[$p->blackId . '|' . $p->whiteId] = true;
                } elseif ($p->isBye()) {
                    $hadBye[$p->whiteId ?? $p->blackId] = true;
                }
            }
        }

        $bye = null;
        if (count($order) % 2 === 1) {
            for ($i = count($order) - 1; $i >= 0; $i--) {
                if (!isset($hadBye[$order[$i]])) {
                    $bye = $order[$i];
                    break;
                }
            }
            $bye ??= $order[count($order) - 1];
            $order = array_values(array_filter($order, static fn(string $id): bool => $id !== $bye));
        }

        $pairings = [];
        $used = [];
        $n = count($order);
        for ($i = 0; $i < $n; $i++) {
            if (isset($used[$order[$i]])) {
                continue;
            }
            $opponent = null;
            for ($j = $i + 1; $j < $n; $j++) {
                if (!isset($used[$order[$j]]) && !isset($met[$order[$i] . '|' . $order[$j]])) {
                    $opponent = $order[$j];
                    break;
                }
            }
            if ($opponent === null) {
                // Forcé : une revanche est tolérée en dernier recours.
                for ($j = $i + 1; $j < $n; $j++) {
                    if (!isset($used[$order[$j]])) {
                        $opponent = $order[$j];
                        break;
                    }
                }
            }
            if ($opponent === null) {
                break;
            }
            $used[$order[$i]] = true;
            $used[$opponent] = true;
            $pairings[] = new Pairing(count($pairings) + 1, $order[$i], $opponent);
        }
        if ($bye !== null) {
            $pairings[] = Pairing::bye(count($pairings) + 1, $bye);
        }

        return new Round($last + 1, $pairings);
    }
}
