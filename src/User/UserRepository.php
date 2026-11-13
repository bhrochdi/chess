<?php

declare(strict_types=1);

namespace LeCercle\User;

/** Port de persistance des comptes. */
interface UserRepository
{
    /** Nouvel identifiant : « u » suivi de 12 caractères hexadécimaux. */
    public function nextId(): string;

    public function add(User $user): void;

    /** Enregistre pseudo, e-mail, rôle, empreinte, suspension et statistiques. */
    public function save(User $user): void;

    public function find(string $id): ?User;

    /** @param string $identifier pseudo ou e-mail, en minuscules */
    public function findByLogin(string $identifier): ?User;

    /** Insensible à la casse. */
    public function pseudoTaken(string $pseudo): bool;

    public function emailTaken(string $email): bool;


    /** Comptes inscrits, invités exclus : décide si une inscription crée le premier administrateur. */
    public function countRegistered(): int;

    public function countAdmins(): int;

    public function delete(string $id): void;

    /** @return list<User> du plus ancien au plus récent */
    public function all(): array;

    /** @return list<User> triés par pseudo */
    public function seenSince(\DateTimeImmutable $since): array;

    /** Met à jour `last_seen_at` uniquement. */
    public function touch(string $id, \DateTimeImmutable $now): void;

    /**
     * Invités sans activité depuis $before : dernière présence, ou création s'ils n'ont jamais été vus.
     *
     * @return list<string>
     */
    public function inactiveGuestIds(\DateTimeImmutable $before): array;
}
