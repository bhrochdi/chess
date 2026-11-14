<?php

declare(strict_types=1);

namespace LeCercle\Chess;

use InvalidArgumentException;

/** Position d'une partie : échiquier, trait, droits de roque, prise en passant et compteurs. Immuable. */
final readonly class Position
{
    /**
     * @param array<int, ?Piece> $board Pièces indexées par Square::index() (0 à 63)
     * @param array<string, int> $kings Case de chaque roi (clé Color::value) ; absente si le roi a été pris
     */
    private function __construct(
        private array $board,
        private Color $sideToMove,
        private CastlingRights $castling,
        private ?Square $enPassant,
        private int $halfmoveClock,
        private int $fullmoveNumber,
        private array $kings,
    ) {}

    public static function initial(): self
    {
        return self::fromFen('rnbqkbnr/pppppppp/8/8/8/8/PPPPPPPP/RNBQKBNR w KQkq - 0 1');
    }

    public static function fromFen(string $fen): self
    {
        $fields = explode(' ', $fen);
        if (count($fields) !== 6) {
            throw new InvalidArgumentException("FEN invalide (6 champs attendus) : '{$fen}'");
        }
        [$placement, $side, $castling, $ep, $halfmove, $fullmove] = $fields;

        $ranks = explode('/', $placement);
        if (count($ranks) !== 8) {
            throw new InvalidArgumentException("FEN invalide (8 rangées attendues) : '{$fen}'");
        }

        $board = array_fill(0, 64, null);
        foreach ($ranks as $i => $row) {
            $rank = 7 - $i;
            $file = 0;
            foreach (str_split($row) as $c) {
                if ($c >= '1' && $c <= '8') {
                    $file += (int) $c;
                    continue;
                }
                if ($file > 7) {
                    throw new InvalidArgumentException("FEN invalide (rangée trop longue) : '{$row}'");
                }
                $board[Square::at($file, $rank)->index()] = Piece::fromFenChar($c);
                ++$file;
            }
            if ($file !== 8) {
                throw new InvalidArgumentException("FEN invalide (rangée de 8 cases attendue) : '{$row}'");
            }
        }

        $kings = [];
        foreach ([Color::White, Color::Black] as $kingColor) {
            $squares = array_keys(array_filter(
                $board,
                static fn(?Piece $p): bool => $p !== null && $p->type === PieceType::King && $p->color === $kingColor,
            ));
            if (count($squares) !== 1) {
                throw new InvalidArgumentException("FEN invalide (un roi exactement par camp attendu) : '{$fen}'");
            }
            $kings[$kingColor->value] = $squares[0];
        }

        $color = match ($side) {
            'w' => Color::White,
            'b' => Color::Black,
            default => throw new InvalidArgumentException("FEN invalide (trait) : '{$side}'"),
        };

        $enPassant = null;
        if ($ep !== '-') {
            if (preg_match('/^[a-h][36]\z/', $ep) !== 1) {
                throw new InvalidArgumentException("FEN invalide (prise en passant) : '{$ep}'");
            }
            // Les blancs au trait : la case est en rangée 6 ; les noirs : rangée 3.
            if ($ep[1] !== ($color === Color::White ? '6' : '3')) {
                throw new InvalidArgumentException("FEN invalide (prise en passant incohérente avec le trait) : '{$ep}'");
            }
            $enPassant = Square::fromAlgebraic($ep);
        }

        if (preg_match('/^(0|[1-9][0-9]{0,8})\z/', $halfmove) !== 1) {
            throw new InvalidArgumentException("FEN invalide (demi-coups) : '{$halfmove}'");
        }
        if (preg_match('/^[1-9][0-9]{0,8}\z/', $fullmove) !== 1) {
            throw new InvalidArgumentException("FEN invalide (numéro de coup) : '{$fullmove}'");
        }

        return new self(
            $board,
            $color,
            CastlingRights::fromFen($castling),
            $enPassant,
            (int) $halfmove,
            (int) $fullmove,
            $kings,
        );
    }

    public function toFen(): string
    {
        return $this->repetitionKey() . ' ' . $this->halfmoveClock . ' ' . $this->fullmoveNumber;
    }

    /** Les quatre premiers champs FEN : identifie une position pour la répétition. */
    public function repetitionKey(): string
    {
        $rows = [];
        for ($rank = 7; $rank >= 0; --$rank) {
            $row = '';
            $empty = 0;
            for ($file = 0; $file < 8; ++$file) {
                $piece = $this->board[$file + 8 * $rank];
                if ($piece === null) {
                    ++$empty;
                    continue;
                }
                $row .= ($empty > 0 ? (string) $empty : '') . $piece->toFenChar();
                $empty = 0;
            }
            $rows[] = $row . ($empty > 0 ? (string) $empty : '');
        }

        return implode('/', $rows)
            . ' ' . $this->sideToMove->value
            . ' ' . $this->castling->toFen()
            . ' ' . ($this->enPassant?->toAlgebraic() ?? '-');
    }

    public function pieceAt(Square $s): ?Piece
    {
        return $this->board[$s->index()];
    }

    public function sideToMove(): Color
    {
        return $this->sideToMove;
    }

    public function castling(): CastlingRights
    {
        return $this->castling;
    }

    public function enPassant(): ?Square
    {
        return $this->enPassant;
    }

    public function halfmoveClock(): int
    {
        return $this->halfmoveClock;
    }

    public function fullmoveNumber(): int
    {
        return $this->fullmoveNumber;
    }

    public function kingSquare(Color $c): ?Square
    {
        $index = $this->kings[$c->value] ?? null;

        return $index === null ? null : Square::fromIndex($index);
    }

    /** Joue le coup sans vérifier sa légalité. */
    public function apply(Move $m): self
    {
        $piece = $this->board[$m->from->index()];
        if ($piece === null) {
            throw new InvalidArgumentException('Aucune pièce sur la case de départ : ' . $m->toUci());
        }

        $board = $this->board;
        $victim = $board[$m->to->index()];
        $captured = $victim !== null || $m->flag === MoveFlag::EnPassant;

        $kings = $this->kings;
        if ($victim?->type === PieceType::King) {
            unset($kings[$victim->color->value]);
        }
        if ($piece->type === PieceType::King) {
            $kings[$piece->color->value] = $m->to->index();
        }

        $board[$m->from->index()] = null;
        $board[$m->to->index()] = $m->promotion === null ? $piece : new Piece($m->promotion, $piece->color);

        if ($m->flag === MoveFlag::EnPassant) {
            $board[Square::at($m->to->file(), $m->from->rank())->index()] = null;
        } elseif ($m->flag === MoveFlag::CastleKingside || $m->flag === MoveFlag::CastleQueenside) {
            $rank = $m->from->rank();
            $kingside = $m->flag === MoveFlag::CastleKingside;
            $rookFrom = Square::at($kingside ? 7 : 0, $rank)->index();
            $rookTo = Square::at($kingside ? 5 : 3, $rank)->index();
            $board[$rookTo] = $board[$rookFrom];
            $board[$rookFrom] = null;
        }

        $castling = $this->castling;
        if ($piece->type === PieceType::King) {
            $castling = $castling->withoutColor($piece->color);
        }
        $castling = $castling->withoutRookSquare($m->from)->withoutRookSquare($m->to);

        $enPassant = null;
        if ($m->flag === MoveFlag::DoublePush) {
            $enPassant = Square::at($m->from->file(), intdiv($m->from->rank() + $m->to->rank(), 2));
        }

        $resetClock = $piece->type === PieceType::Pawn || $captured;

        return new self(
            $board,
            $this->sideToMove->opponent(),
            $castling,
            $enPassant,
            $resetClock ? 0 : $this->halfmoveClock + 1,
            $this->fullmoveNumber + ($this->sideToMove === Color::Black ? 1 : 0),
            $kings,
        );
    }
}
