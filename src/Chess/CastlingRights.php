<?php

declare(strict_types=1);

namespace LeCercle\Chess;

use InvalidArgumentException;

/** Droits de roque des deux camps. */
final readonly class CastlingRights
{
    public function __construct(
        public bool $whiteKingside,
        public bool $whiteQueenside,
        public bool $blackKingside,
        public bool $blackQueenside,
    ) {}

    /** Champ FEN : sous-ensemble de « KQkq » sans doublon, ou « - ». */
    public static function fromFen(string $s): self
    {
        if ($s === '-') {
            return new self(false, false, false, false);
        }
        if (preg_match('/^[KQkq]{1,4}\z/', $s) !== 1 || strlen(count_chars($s, 3)) !== strlen($s)) {
            throw new InvalidArgumentException("Droits de roque FEN invalides : '{$s}'");
        }

        return new self(
            str_contains($s, 'K'),
            str_contains($s, 'Q'),
            str_contains($s, 'k'),
            str_contains($s, 'q'),
        );
    }

    public function toFen(): string
    {
        $fen = ($this->whiteKingside ? 'K' : '')
            . ($this->whiteQueenside ? 'Q' : '')
            . ($this->blackKingside ? 'k' : '')
            . ($this->blackQueenside ? 'q' : '');

        return $fen === '' ? '-' : $fen;
    }

    public function withoutColor(Color $c): self
    {
        return $c === Color::White
            ? new self(false, false, $this->blackKingside, $this->blackQueenside)
            : new self($this->whiteKingside, $this->whiteQueenside, false, false);
    }

    /** Retire le droit lié à la tour d'origine posée sur cette case (a1, h1, a8, h8). */
    public function withoutRookSquare(Square $s): self
    {
        return match ($s->index()) {
            7 => new self(false, $this->whiteQueenside, $this->blackKingside, $this->blackQueenside),
            0 => new self($this->whiteKingside, false, $this->blackKingside, $this->blackQueenside),
            63 => new self($this->whiteKingside, $this->whiteQueenside, false, $this->blackQueenside),
            56 => new self($this->whiteKingside, $this->whiteQueenside, $this->blackKingside, false),
            default => $this,
        };
    }

    /** Vrai si le camp conserve le droit de roque du côté donné ; faux pour tout autre drapeau. */
    public function allows(Color $c, MoveFlag $side): bool
    {
        return match ($side) {
            MoveFlag::CastleKingside => $c === Color::White ? $this->whiteKingside : $this->blackKingside,
            MoveFlag::CastleQueenside => $c === Color::White ? $this->whiteQueenside : $this->blackQueenside,
            default => false,
        };
    }
}
