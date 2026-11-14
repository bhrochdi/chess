<?php

declare(strict_types=1);

namespace LeCercle\Tests\Unit\Chess\Ai;

use LeCercle\Chess\Ai\RandomSource;

/** Source aléatoire constante, pour des tests déterministes. */
final readonly class FixedRandom implements RandomSource
{
    public function __construct(private float $value) {}

    public function float(): float
    {
        return $this->value;
    }
}
