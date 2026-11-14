<?php

declare(strict_types=1);

namespace LeCercle\Shared;

/** Conversion entre instants et millisecondes depuis l'époque Unix (format des réponses JSON). */
final class Millis
{
    public static function of(\DateTimeImmutable $t): int
    {
        // 'U' arrondit vers le bas et 'v' est toujours positif : on les additionne plutôt que de concaténer.
        return (int) $t->format('U') * 1000 + (int) $t->format('v');
    }

    public static function toDateTime(int $ms): \DateTimeImmutable
    {
        // Division par défaut : le reste est toujours dans 0..999, même avant 1970.
        $seconds = intdiv($ms, 1000) - ($ms % 1000 < 0 ? 1 : 0);

        return (new \DateTimeImmutable('@' . $seconds))
            ->setTimezone(new \DateTimeZone('UTC'))
            ->modify(\sprintf('+%d msec', $ms - $seconds * 1000));
    }
}
