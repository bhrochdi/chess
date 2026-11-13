<?php

declare(strict_types=1);

namespace LeCercle\Infra\Db;

/** Conversion entre DateTimeImmutable et les colonnes DATETIME(3) stockées en UTC. */
final class SqlTime
{
    public static function format(\DateTimeImmutable $t): string
    {
        return $t->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s.v');
    }

    public static function parse(string $value): \DateTimeImmutable
    {
        $utc = new \DateTimeZone('UTC');
        foreach (['!Y-m-d H:i:s.v', '!Y-m-d H:i:s'] as $pattern) {
            $t = \DateTimeImmutable::createFromFormat($pattern, $value, $utc);
            // Le format ré-émis doit redonner la saisie : écarte les débordements (mois 13, jour 45).
            if ($t !== false && $t->format(substr($pattern, 1)) === $value) {
                return $t;
            }
        }

        throw new \InvalidArgumentException("Date SQL invalide : « $value ».");
    }
}
