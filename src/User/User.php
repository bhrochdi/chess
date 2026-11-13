<?php

declare(strict_types=1);

namespace LeCercle\User;

use LeCercle\Shared\Role;

/** Compte utilisateur (local, invité ou fournisseur externe). Immuable. */
final readonly class User
{
    public function __construct(
        public string $id,
        public string $pseudo,
        public ?string $email,
        public Provider $provider,
        public Role $role,
        public ?string $passwordHash,
        public bool $suspended,
        public \DateTimeImmutable $createdAt,
        public ?\DateTimeImmutable $lastSeenAt = null,
    ) {}

    public static function registered(
        string $id,
        string $pseudo,
        string $email,
        string $passwordHash,
        Role $role,
        \DateTimeImmutable $now,
    ): self {
        return new self($id, $pseudo, $email, Provider::Local, $role, $passwordHash, false, $now);
    }

    public static function guest(string $id, string $pseudo, \DateTimeImmutable $now): self
    {
        return new self($id, $pseudo, null, Provider::Guest, Role::User, null, false, $now);
    }

    public function withRole(Role $role): self
    {
        return new self(
            $this->id,
            $this->pseudo,
            $this->email,
            $this->provider,
            $role,
            $this->passwordHash,
            $this->suspended,
            $this->createdAt,
            $this->lastSeenAt,
        );
    }

    public function withSuspended(bool $suspended): self
    {
        return new self(
            $this->id,
            $this->pseudo,
            $this->email,
            $this->provider,
            $this->role,
            $this->passwordHash,
            $suspended,
            $this->createdAt,
            $this->lastSeenAt,
        );
    }

    public function withPasswordHash(string $hash): self
    {
        return new self(
            $this->id,
            $this->pseudo,
            $this->email,
            $this->provider,
            $this->role,
            $hash,
            $this->suspended,
            $this->createdAt,
            $this->lastSeenAt,
        );
    }

    public function canLogInWithPassword(): bool
    {
        return $this->provider === Provider::Local && $this->passwordHash !== null && $this->passwordHash !== '';
    }
}
