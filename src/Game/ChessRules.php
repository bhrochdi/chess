<?php

declare(strict_types=1);

namespace LeCercle\Game;

use LeCercle\Chess\Arbiter;
use LeCercle\Chess\AttackDetector;
use LeCercle\Chess\Move;
use LeCercle\Chess\MoveGenerator;
use LeCercle\Chess\Outcome;
use LeCercle\Chess\Position;
use LeCercle\Chess\SanFormatter;
use LeCercle\Shared\ValidationException;

/** Façade du domaine échecs pour les parties : validation des coups UCI, notation et fin de partie. */
final class ChessRules
{
    public function __construct(
        private readonly MoveGenerator $moves,
        private readonly SanFormatter $san,
        private readonly Arbiter $arbiter,
    ) {}

    public static function standard(): self
    {
        $moves = new MoveGenerator(new AttackDetector());

        return new self($moves, new SanFormatter($moves), new Arbiter($moves));
    }

    public function generator(): MoveGenerator
    {
        return $this->moves;
    }

    /** Coup légal de $p désigné par son UCI ; sinon « Coup illégal. » (422). */
    public function moveFromUci(Position $p, string $uci): Move
    {
        if (preg_match('/\A[a-h][1-8][a-h][1-8][qrbn]?\z/', $uci) === 1) {
            foreach ($this->moves->legalMoves($p) as $move) {
                if ($move->toUci() === $uci) {
                    return $move;
                }
            }
        }

        throw new ValidationException('Coup illégal.');
    }

    public function san(Position $before, Move $m): string
    {
        return $this->san->format($before, $m);
    }

    /** @param list<string> $repetitionKeys */
    public function outcome(Position $after, array $repetitionKeys): ?Outcome
    {
        return $this->arbiter->outcome($after, $repetitionKeys);
    }

    /** @return list<string> */
    public function legalUcis(Position $p): array
    {
        return array_map(static fn(Move $m): string => $m->toUci(), $this->moves->legalMoves($p));
    }

    public function inCheck(Position $p): bool
    {
        return $this->moves->isInCheck($p);
    }
}
