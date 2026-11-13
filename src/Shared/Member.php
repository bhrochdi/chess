<?php

declare(strict_types=1);

namespace LeCercle\Shared;

/** Compte tel que les services le voient : identité, rôle et état. */
final readonly class Member
{
    public function __construct(
        public string $id,
        public string $pseudo,
        public Role $role,
        public bool $suspended,
        public bool $guest,
    ) {}

    public function isAdmin(): bool
    {
        return $this->role === Role::Admin;
    }
}
