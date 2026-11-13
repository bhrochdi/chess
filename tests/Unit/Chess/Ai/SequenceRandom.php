<?php

declare(strict_types=1);

namespace LeCercle\Tests\Unit\Chess\Ai;

use InvalidArgumentException;
use LeCercle\Chess\Ai\RandomSource;

/** Source aléatoire qui rend des valeurs fixées dans l'ordre, en boucle, pour des tests déterministes. */
final class SequenceRandom implements RandomSource
{
    private int $next = 0;

    /** @param list<float> $values */
    public function __construct(private readonly array $values)
    {
        if ($values === []) {
            throw new InvalidArgumentException('La séquence ne doit pas être vide.');
        }
    }

    public function float(): float
    {
        $value = $this->values[$this->next];
        $this->next = ($this->next + 1) % count($this->values);

        return $value;
    }
}
