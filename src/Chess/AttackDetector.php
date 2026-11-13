<?php

declare(strict_types=1);

namespace LeCercle\Chess;

/** Détermine si une case est attaquée par un camp. */
final class AttackDetector
{
    public function isAttacked(Position $p, Square $s, Color $by): bool
    {
        // Un pion blanc attaque vers le haut : il se trouve donc une rangée plus bas.
        $pawnRank = $by === Color::White ? -1 : 1;
        foreach ([-1, 1] as $df) {
            if ($this->holds($p, $s->offset($df, $pawnRank), $by, PieceType::Pawn)) {
                return true;
            }
        }
        foreach (BoardGeometry::knightTargets($s) as $t) {
            if ($this->holds($p, $t, $by, PieceType::Knight)) {
                return true;
            }
        }

        return $this->attackedAlongRays($p, $by, BoardGeometry::orthogonalRays($s), PieceType::Rook)
            || $this->attackedAlongRays($p, $by, BoardGeometry::diagonalRays($s), PieceType::Bishop);
    }

    /** Vrai si le roi du camp est attaqué ; faux s'il n'y a pas de roi. */
    public function isInCheck(Position $p, Color $c): bool
    {
        $king = $p->kingSquare($c);

        return $king !== null && $this->isAttacked($p, $king, $c->opponent());
    }

    private function holds(Position $p, ?Square $s, Color $color, PieceType $type): bool
    {
        if ($s === null) {
            return false;
        }
        $piece = $p->pieceAt($s);

        return $piece !== null && $piece->color === $color && $piece->type === $type;
    }

    /**
     * Cherche le long des rayons une pièce glissante ($slider ou dame), ou le roi adjacent.
     *
     * @param list<list<Square>> $rays
     */
    private function attackedAlongRays(Position $p, Color $by, array $rays, PieceType $slider): bool
    {
        foreach ($rays as $ray) {
            foreach ($ray as $i => $t) {
                $piece = $p->pieceAt($t);
                if ($piece === null) {
                    continue;
                }
                if ($piece->color === $by && (
                    $piece->type === $slider
                    || $piece->type === PieceType::Queen
                    || ($piece->type === PieceType::King && $i === 0)
                )) {
                    return true;
                }
                break;
            }
        }

        return false;
    }
}
