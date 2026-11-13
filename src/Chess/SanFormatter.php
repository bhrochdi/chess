<?php

declare(strict_types=1);

namespace LeCercle\Chess;

/** Notation algébrique abrégée (SAN, lettres anglaises du PGN) avec suffixe `+` ou `#`. */
final class SanFormatter
{
    private const FILES = 'abcdefgh';

    public function __construct(private readonly MoveGenerator $moves) {}

    /** @param Position $before Position avant le coup, qui doit y être légal. */
    public function format(Position $before, Move $move): string
    {
        return $this->body($before, $move) . $this->suffix($before->apply($move));
    }

    private function body(Position $before, Move $move): string
    {
        if ($move->flag === MoveFlag::CastleKingside) {
            return 'O-O';
        }
        if ($move->flag === MoveFlag::CastleQueenside) {
            return 'O-O-O';
        }

        $piece = $before->pieceAt($move->from);
        if ($piece === null) {
            throw new \InvalidArgumentException('Aucune pièce sur la case de départ du coup.');
        }
        $capture = $before->pieceAt($move->to) !== null || $move->flag === MoveFlag::EnPassant;
        $dest = $move->to->toAlgebraic();

        if ($piece->type === PieceType::Pawn) {
            $text = ($capture ? self::FILES[$move->from->file()] . 'x' : '') . $dest;

            return $move->promotion === null ? $text : $text . '=' . $move->promotion->value;
        }

        return $piece->type->value
            . $this->disambiguation($before, $move, $piece)
            . ($capture ? 'x' : '')
            . $dest;
    }

    /** Colonne si elle distingue les rivales, sinon rangée, sinon les deux. */
    private function disambiguation(Position $before, Move $move, Piece $piece): string
    {
        $sameFile = false;
        $sameRank = false;
        $ambiguous = false;
        for ($i = 0; $i < 64; ++$i) {
            $other = Square::fromIndex($i);
            if ($other->index() === $move->from->index()) {
                continue;
            }
            $q = $before->pieceAt($other);
            if ($q === null || $q->type !== $piece->type || $q->color !== $piece->color) {
                continue;
            }
            foreach ($this->moves->legalMovesFrom($before, $other) as $m) {
                if ($m->to->index() === $move->to->index()) {
                    $ambiguous = true;
                    $sameFile = $sameFile || $other->file() === $move->from->file();
                    $sameRank = $sameRank || $other->rank() === $move->from->rank();
                    break;
                }
            }
        }

        if (!$ambiguous) {
            return '';
        }
        $file = self::FILES[$move->from->file()];
        $rank = (string) ($move->from->rank() + 1);
        if (!$sameFile) {
            return $file;
        }

        return $sameRank ? $file . $rank : $rank;
    }

    /** `#` si mat, `+` si simple échec, vide sinon ; `$after` a le trait à l'adversaire. */
    private function suffix(Position $after): string
    {
        if (!$this->moves->isInCheck($after)) {
            return '';
        }

        return $this->moves->legalMoves($after) === [] ? '#' : '+';
    }
}
