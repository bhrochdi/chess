<?php

declare(strict_types=1);

namespace LeCercle\User;

use LeCercle\Shared\ValidationException;

/** Règles de saisie des champs d'un compte. */
final class UserFields
{
    public static function pseudo(string $pseudo): string
    {
        $pseudo = trim($pseudo);
        if (preg_match('/\A[A-Za-z0-9_-]{2,16}\z/', $pseudo) !== 1) {
            throw new ValidationException('Pseudo : 2 à 16 caractères (lettres, chiffres, - _).');
        }

        return $pseudo;
    }

    public static function email(string $email): string
    {
        $email = strtolower(trim($email));
        if (strlen($email) > 120 || preg_match('/\A[^\s@]+@[^\s@]+\.[^\s@]{2,}\z/', $email) !== 1) {
            throw new ValidationException('Adresse e-mail invalide.');
        }

        return $email;
    }

    /** Pas de trim : bcrypt ignore tout au-delà de 72 octets. */
    public static function password(string $password): string
    {
        if (strlen($password) < 6) {
            throw new ValidationException('Mot de passe : 6 caractères minimum.');
        }
        if (strlen($password) > 72) {
            throw new ValidationException('Mot de passe : 72 caractères maximum.');
        }

        return $password;
    }
}
