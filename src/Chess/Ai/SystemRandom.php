<?php

declare(strict_types=1);

namespace LeCercle\Chess\Ai;

/** Aléa du système (CSPRNG de PHP). */
final class SystemRandom implements RandomSource
{
    public function float(): float
    {
        return self::unit(random_int(0, 2 ** 53 - 1));
    }

    /** Ramène un tirage de 53 bits dans [0, 1) : exact en flottant, 1.0 jamais atteint. */
    public static function unit(int $draw): float
    {
        return $draw / 2 ** 53;
    }
}
