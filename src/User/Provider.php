<?php

declare(strict_types=1);

namespace LeCercle\User;

/** Origine d'un compte (valeurs de `users.provider`). */
enum Provider: string
{
    case Local = 'local';
    case Guest = 'guest';
    case Google = 'google';
    case Facebook = 'facebook';
}
