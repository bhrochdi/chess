<?php

declare(strict_types=1);

namespace LeCercle\Chess;

/** Coup pseudo-légal ou légal, indépendant de la position. */
final readonly class Move
{
    public function __construct(
        public Square $from,
        public Square $to,
        public MoveFlag $flag = MoveFlag::Normal,
        public ?PieceType $promotion = null,
    ) {}

    /** Format UCI (`e2e4`, `e7e8q`), format d'échange avec le front. */
    public function toUci(): string
    {
        return $this->from->toAlgebraic()
            . $this->to->toAlgebraic()
            . ($this->promotion === null ? '' : strtolower($this->promotion->value));
    }

    public function equals(Move $o): bool
    {
        return $this->from->index() === $o->from->index()
            && $this->to->index() === $o->to->index()
            && $this->flag === $o->flag
            && $this->promotion === $o->promotion;
    }
}
