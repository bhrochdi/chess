<?php

declare(strict_types=1);

namespace LeCercle\Tournament;

use LeCercle\Shared\NotFoundException;

/** Port de stockage des tournois. */
interface TournamentRepository
{
    /** 't' suivi de 12 chiffres hexadécimaux. */
    public function nextId(): string;

    public function add(Tournament $t): void;

    /** @throws NotFoundException 'Tournoi introuvable.' */
    public function get(string $id): Tournament;

    /**
     * Comme get(), en verrouillant le tournoi jusqu'à la fin de la transaction.
     *
     * @throws NotFoundException 'Tournoi introuvable.'
     */
    public function getForUpdate(string $id): Tournament;

    public function save(Tournament $t): void;

    /** Le tournoi dont un appariement porte cette partie, verrouillé jusqu'à la fin de la transaction ; null sinon. */
    public function findByGameId(string $gameId): ?Tournament;

    public function delete(string $id): void;

    /** @return list<TournamentSummary> */
    public function summaries(): array;

    /** Insensible à la casse ; sous MySQL, la collation ignore aussi les accents. */
    public function nameTaken(string $name, ?string $exceptId = null): bool;

    public function runningExists(?string $exceptId = null): bool;
}
