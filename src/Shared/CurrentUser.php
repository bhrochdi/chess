<?php

declare(strict_types=1);

namespace LeCercle\Shared;

/** L'utilisateur connecté pour la requête en cours. */
interface CurrentUser
{
    public function id(): ?string;

    /** @throws ForbiddenException 'Connexion requise.' */
    public function requireId(): string;
}
