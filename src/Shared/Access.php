<?php

declare(strict_types=1);

namespace LeCercle\Shared;

/** Garde d'accès : résout l'acteur de la requête et refuse ce qu'il n'a pas le droit de faire. */
final class Access
{
    public const LOGIN_REQUIRED = 'Connexion requise.';
    public const SUSPENDED = 'Compte suspendu par l’administration.';
    public const ADMIN_ONLY = 'Action réservée aux administrateurs.';

    public function __construct(private readonly CurrentUser $user, private readonly Members $members) {}

    /** Le visiteur connecté, même suspendu (les lectures restent permises) ; null sinon. */
    public function viewer(): ?Member
    {
        $id = $this->user->id();

        return $id === null ? null : $this->members->find($id);
    }

    /** @throws ForbiddenException non connecté ou compte suspendu */
    public function member(): Member
    {
        $member = $this->viewer() ?? throw new ForbiddenException(self::LOGIN_REQUIRED);

        return $member->suspended ? throw new ForbiddenException(self::SUSPENDED) : $member;
    }

    /** @throws ForbiddenException */
    public function admin(): Member
    {
        $member = $this->member();

        return $member->isAdmin() ? $member : throw new ForbiddenException(self::ADMIN_ONLY);
    }
}
