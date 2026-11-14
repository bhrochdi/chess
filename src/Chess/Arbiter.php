<?php

declare(strict_types=1);

namespace LeCercle\Chess;

/** Détecte la fin de partie après un coup, dans l'ordre de js/app.js. */
final class Arbiter
{
    public function __construct(private readonly MoveGenerator $moves) {}

    /**
     * @param list<string> $repetitionKeys clé de répétition de chaque position de la partie, la position courante comprise
     */
    public function outcome(Position $p, array $repetitionKeys): ?Outcome
    {
        if ($this->moves->legalMoves($p) === []) {
            if (!$this->moves->isInCheck($p)) {
                return new Outcome(Result::Draw, EndReason::Stalemate);
            }
            $winner = $p->sideToMove() === Color::White ? Result::BlackWins : Result::WhiteWins;

            return new Outcome($winner, EndReason::Checkmate);
        }
        if ($p->halfmoveClock() >= 100) {
            return new Outcome(Result::Draw, EndReason::FiftyMoves);
        }
        if ($this->isThreefold($p, $repetitionKeys)) {
            return new Outcome(Result::Draw, EndReason::Threefold);
        }
        if ($this->isInsufficientMaterial($p)) {
            return new Outcome(Result::Draw, EndReason::InsufficientMaterial);
        }

        return null;
    }

    /** @param list<string> $keys */
    private function isThreefold(Position $p, array $keys): bool
    {
        $current = $p->repetitionKey();
        $count = 0;
        foreach ($keys as $key) {
            if ($key === $current) {
                ++$count;
            }
        }

        return $count >= 3;
    }

    /** Ni pion, tour ou dame, et au plus un fou ou un cavalier en tout. */
    private function isInsufficientMaterial(Position $p): bool
    {
        $minors = 0;
        for ($i = 0; $i < 64; ++$i) {
            $piece = $p->pieceAt(Square::fromIndex($i));
            if ($piece === null) {
                continue;
            }
            switch ($piece->type) {
                case PieceType::Pawn:
                case PieceType::Rook:
                case PieceType::Queen:
                    return false;
                case PieceType::Bishop:
                case PieceType::Knight:
                    ++$minors;
                    break;
                case PieceType::King:
                    break;
            }
        }

        return $minors <= 1;
    }
}
