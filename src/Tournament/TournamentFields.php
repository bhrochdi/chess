<?php

declare(strict_types=1);

namespace LeCercle\Tournament;

use LeCercle\Shared\ValidationException;

/** Règles de saisie des champs d'un tournoi. */
final class TournamentFields
{
    public static function name(string $name): string
    {
        $name = trim($name);
        if (mb_strlen($name) < 2) {
            throw new ValidationException('Nom trop court (2 caractères minimum).');
        }
        if (mb_strlen($name) > 60) {
            throw new ValidationException('Nom trop long (60 caractères maximum).');
        }

        return $name;
    }

    public static function description(string $d): string
    {
        $d = trim($d);
        if (mb_strlen($d) > 500) {
            throw new ValidationException('Description trop longue (500 caractères maximum).');
        }

        return $d;
    }

    public static function plannedRounds(?int $rounds): int
    {
        if ($rounds === null) {
            return 5;
        }
        if ($rounds < 2 || $rounds > 15) {
            throw new ValidationException('Nombre de rondes : entre 2 et 15.');
        }

        return $rounds;
    }
}
