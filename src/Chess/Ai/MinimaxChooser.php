<?php

declare(strict_types=1);

namespace LeCercle\Chess\Ai;

use InvalidArgumentException;
use LeCercle\Chess\Color;
use LeCercle\Chess\Move;
use LeCercle\Chess\MoveGenerator;
use LeCercle\Chess\Position;

/** Minimax avec élagage alpha-bêta, port de `aiBestMove` de l'ancien js/engine.js. */
final class MinimaxChooser implements MoveChooser
{
    private const MATE = 99999;

    public function __construct(
        private readonly MoveGenerator $moves,
        private readonly Evaluator $evaluator,
        private readonly RandomSource $random,
        private readonly int $depth,
        private readonly int $fuzz,
    ) {
        if ($depth < 1) {
            throw new InvalidArgumentException("Profondeur de recherche invalide (au moins 1) : {$depth}");
        }
        if ($fuzz < 0) {
            throw new InvalidArgumentException("Bruit invalide (positif ou nul) : {$fuzz}");
        }
    }

    public function choose(Position $p): ?Move
    {
        $moves = $this->ordered($p);
        if ($moves === []) {
            return null;
        }

        $black = $p->sideToMove() === Color::Black;
        $best = null;
        $bestScore = -INF;
        foreach ($moves as $move) {
            // Borne à la racine : minimax est « fail-soft », donc un coup dont la valeur exacte
            // dépasse la borne la reçoit exactement ; un coup à la borne ou en dessous reçoit une
            // valeur ≤ borne, et borne + bruit × r < meilleur score (r < 1) : il n'aurait pas été
            // choisi avec sa valeur exacte non plus. Sans bruit, ≤ meilleur score n'est pas « > ».
            [$alpha, $beta] = [PHP_INT_MIN, PHP_INT_MAX];
            if ($best !== null) {
                $bound = (int) floor($bestScore - $this->fuzz);
                // Score vu des Blancs pour minimax ; les Noirs comparent -v.
                [$alpha, $beta] = $black ? [PHP_INT_MIN, -$bound] : [$bound, PHP_INT_MAX];
            }
            $child = $p->apply($move);
            $score = (float) $this->minimax(
                $child,
                $this->depth - 1,
                $alpha,
                $beta,
                $child->sideToMove() === Color::White,
            );
            if ($black) {
                $score = -$score;
            }
            $score += $this->fuzz * $this->random->float();
            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $move;
            }
        }

        return $best;
    }

    private function minimax(Position $p, int $depth, int $alpha, int $beta, bool $maximizing): int
    {
        if ($depth === 0) {
            return $this->evaluator->evaluate($p);
        }

        $moves = $this->ordered($p);
        if ($moves === []) {
            if ($this->moves->isInCheck($p)) {
                return $maximizing ? -self::MATE - $depth : self::MATE + $depth;
            }

            return 0;
        }

        if ($maximizing) {
            $best = PHP_INT_MIN;
            foreach ($moves as $move) {
                $best = max($best, $this->minimax($p->apply($move), $depth - 1, $alpha, $beta, false));
                $alpha = max($alpha, $best);
                if ($beta <= $alpha) {
                    break;
                }
            }

            return $best;
        }

        $best = PHP_INT_MAX;
        foreach ($moves as $move) {
            $best = min($best, $this->minimax($p->apply($move), $depth - 1, $alpha, $beta, true));
            $beta = min($beta, $best);
            if ($beta <= $alpha) {
                break;
            }
        }

        return $best;
    }

    /**
     * Coups légaux, captures les plus chères d'abord, ordre de génération conservé à valeur égale
     * (prise en passant comptée 0, case d'arrivée vide).
     *
     * @return list<Move>
     */
    private function ordered(Position $p): array
    {
        $buckets = [];
        foreach ($this->moves->legalMoves($p) as $m) {
            $victim = $p->pieceAt($m->to);
            $buckets[$victim === null ? 0 : PieceValues::of($victim->type)][] = $m;
        }
        krsort($buckets);

        return array_merge(...array_values($buckets));
    }
}
