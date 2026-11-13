<?php

declare(strict_types=1);

namespace LeCercle\Chess;

/** Génère les coups légaux : coups pseudo-légaux filtrés par l'absence d'échec au roi. */
final class MoveGenerator
{
    private const PROMOTIONS = [PieceType::Queen, PieceType::Rook, PieceType::Bishop, PieceType::Knight];
    public function __construct(private readonly AttackDetector $attacks) {}

    /** @return list<Move> */
    public function legalMoves(Position $p): array
    {
        $side = $p->sideToMove();
        $king = $p->kingSquare($side);
        $inCheck = $this->attacks->isInCheck($p, $side);
        $moves = [];
        for ($i = 0; $i < 64; ++$i) {
            $from = Square::fromIndex($i);
            $piece = $p->pieceAt($from);
            if ($piece !== null && $piece->color === $side) {
                array_push($moves, ...$this->legalFiltered($p, $from, $piece, $king, $inCheck));
            }
        }

        return $moves;
    }

    /** @return list<Move> Vide si la case est vide ou porte une pièce du camp qui n'a pas le trait. */
    public function legalMovesFrom(Position $p, Square $from): array
    {
        $piece = $p->pieceAt($from);
        if ($piece === null || $piece->color !== $p->sideToMove()) {
            return [];
        }

        return $this->legalFiltered(
            $p,
            $from,
            $piece,
            $p->kingSquare($piece->color),
            $this->attacks->isInCheck($p, $piece->color),
        );
    }

    /** Vrai si le camp qui a le trait est en échec. */
    public function isInCheck(Position $p): bool
    {
        return $this->attacks->isInCheck($p, $p->sideToMove());
    }

    /**
     * Sans roi ($king null), chaque coup passe par le test complet, comme sans ce raccourci.
     *
     * @return list<Move>
     */
    private function legalFiltered(Position $p, Square $from, Piece $piece, ?Square $king, bool $inCheck): array
    {
        // Une pièce hors de toute ligne passant par son roi ne peut pas découvrir d'échec.
        $offLines = false;
        if ($king !== null && !$inCheck && $piece->type !== PieceType::King) {
            $df = $from->file() - $king->file();
            $dr = $from->rank() - $king->rank();
            $offLines = $df !== 0 && $dr !== 0 && abs($df) !== abs($dr);
        }

        $legal = [];
        foreach ($this->pseudoMoves($p, $from, $piece) as $m) {
            if (($offLines && $m->flag !== MoveFlag::EnPassant)
                || !$this->attacks->isInCheck($p->apply($m), $piece->color)) {
                $legal[] = $m;
            }
        }

        return $legal;
    }

    /** @return list<Move> */
    private function pseudoMoves(Position $p, Square $from, Piece $piece): array
    {
        return match ($piece->type) {
            PieceType::Pawn => $this->pawnMoves($p, $from, $piece->color),
            PieceType::Knight => $this->stepMoves($p, $from, $piece->color, BoardGeometry::knightTargets($from)),
            PieceType::Bishop => $this->slideMoves($p, $from, $piece->color, BoardGeometry::diagonalRays($from)),
            PieceType::Rook => $this->slideMoves($p, $from, $piece->color, BoardGeometry::orthogonalRays($from)),
            PieceType::Queen => $this->slideMoves($p, $from, $piece->color, BoardGeometry::queenRays($from)),
            PieceType::King => [
                ...$this->stepMoves($p, $from, $piece->color, BoardGeometry::kingTargets($from)),
                ...$this->castlingMoves($p, $from, $piece->color),
            ],
        };
    }

    /** @return list<Move> */
    private function pawnMoves(Position $p, Square $from, Color $color): array
    {
        $dir = $color === Color::White ? 1 : -1;
        $startRank = $color === Color::White ? 1 : 6;
        $moves = [];

        $one = $from->offset(0, $dir);
        if ($one !== null && $p->pieceAt($one) === null) {
            $this->addPawnMove($moves, $from, $one);
            $two = $from->offset(0, 2 * $dir);
            if ($from->rank() === $startRank && $two !== null && $p->pieceAt($two) === null) {
                $moves[] = new Move($from, $two, MoveFlag::DoublePush);
            }
        }

        foreach ([-1, 1] as $df) {
            $to = $from->offset($df, $dir);
            if ($to === null) {
                continue;
            }
            $target = $p->pieceAt($to);
            if ($target !== null && $target->color !== $color) {
                $this->addPawnMove($moves, $from, $to);
            } elseif ($target === null && $p->enPassant()?->index() === $to->index()) {
                $moves[] = new Move($from, $to, MoveFlag::EnPassant);
            }
        }

        return $moves;
    }

    /** @param list<Move> $moves Reçoit un coup, ou quatre s'il atteint la dernière rangée. */
    private function addPawnMove(array &$moves, Square $from, Square $to): void
    {
        if ($to->rank() !== 0 && $to->rank() !== 7) {
            $moves[] = new Move($from, $to);

            return;
        }
        foreach (self::PROMOTIONS as $type) {
            $moves[] = new Move($from, $to, MoveFlag::Normal, $type);
        }
    }

    /**
     * @param list<Square> $targets
     *
     * @return list<Move>
     */
    private function stepMoves(Position $p, Square $from, Color $color, array $targets): array
    {
        $moves = [];
        foreach ($targets as $to) {
            if ($p->pieceAt($to)?->color !== $color) {
                $moves[] = new Move($from, $to);
            }
        }

        return $moves;
    }

    /**
     * @param list<list<Square>> $rays
     *
     * @return list<Move>
     */
    private function slideMoves(Position $p, Square $from, Color $color, array $rays): array
    {
        $moves = [];
        foreach ($rays as $ray) {
            foreach ($ray as $to) {
                $target = $p->pieceAt($to);
                if ($target === null || $target->color !== $color) {
                    $moves[] = new Move($from, $to);
                }
                if ($target !== null) {
                    break;
                }
            }
        }

        return $moves;
    }

    /** @return list<Move> */
    private function castlingMoves(Position $p, Square $from, Color $color): array
    {
        $rank = $color === Color::White ? 0 : 7;
        $enemy = $color->opponent();
        if ($from->index() !== Square::at(4, $rank)->index() || $this->attacks->isAttacked($p, $from, $enemy)) {
            return [];
        }

        $moves = [];
        // [drapeau, colonne de la tour, colonnes à vider, colonnes que le roi traverse ou atteint]
        $sides = [
            [MoveFlag::CastleKingside, 7, [5, 6], [5, 6]],
            [MoveFlag::CastleQueenside, 0, [1, 2, 3], [3, 2]],
        ];
        foreach ($sides as [$flag, $rookFile, $emptyFiles, $kingPath]) {
            if (!$p->castling()->allows($color, $flag)) {
                continue;
            }
            $rook = $p->pieceAt(Square::at($rookFile, $rank));
            if ($rook === null || $rook->type !== PieceType::Rook || $rook->color !== $color) {
                continue;
            }
            foreach ($emptyFiles as $file) {
                if ($p->pieceAt(Square::at($file, $rank)) !== null) {
                    continue 2;
                }
            }
            foreach ($kingPath as $file) {
                if ($this->attacks->isAttacked($p, Square::at($file, $rank), $enemy)) {
                    continue 2;
                }
            }
            $moves[] = new Move($from, Square::at($flag === MoveFlag::CastleKingside ? 6 : 2, $rank), $flag);
        }

        return $moves;
    }
}
