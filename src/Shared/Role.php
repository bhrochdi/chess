<?php

declare(strict_types=1);

namespace LeCercle\Shared;

/** Rôle d'un compte (valeurs de `users.role`). */
enum Role: string
{
    case User = 'user';
    case Admin = 'admin';
}
