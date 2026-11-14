<?php

declare(strict_types=1);

namespace LeCercle\Chess;

use InvalidArgumentException;

/** Pièce posée sur l'échiquier : un type et une couleur. */
final readonly class Piece
{
    public function __construct(
        public PieceType $type,
        public Color $color,
    ) {}

    public static function fromFenChar(string $c): self
    {
        $type = PieceType::tryFrom(strtoupper($c));
        if ($type === null || strlen($c) !== 1 || !ctype_alpha($c)) {
            throw new InvalidArgumentException("Caractère de pièce FEN invalide : '{$c}'");
        }

        return new self($type, ctype_upper($c) ? Color::White : Color::Black);
    }

    /** Majuscule pour les blancs, minuscule pour les noirs. */
    public function toFenChar(): string
    {
        return $this->color === Color::White
            ? $this->type->value
            : strtolower($this->type->value);
    }
}
