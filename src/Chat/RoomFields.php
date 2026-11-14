<?php

declare(strict_types=1);

namespace LeCercle\Chat;

use LeCercle\Shared\ValidationException;

/** Règles des champs d'un salon. */
final class RoomFields
{
    public static function name(string $name): string
    {
        $name = trim($name);
        if (mb_strlen($name) < 2) {
            throw new ValidationException('Nom trop court (2 caractères minimum).');
        }
        if (mb_strlen($name) > 40) {
            throw new ValidationException('Nom trop long (40 caractères maximum).');
        }

        return $name;
    }

    public static function description(string $description): string
    {
        $description = trim($description);
        if (mb_strlen($description) > 200) {
            throw new ValidationException('Description trop longue (200 caractères maximum).');
        }

        return $description;
    }
}
