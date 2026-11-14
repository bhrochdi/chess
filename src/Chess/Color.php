<?php

declare(strict_types=1);

namespace LeCercle\Chess;

enum Color: string
{
    case White = 'w';
    case Black = 'b';

    public function opponent(): self
    {
        return match ($this) {
            self::White => self::Black,
            self::Black => self::White,
        };
    }
}
