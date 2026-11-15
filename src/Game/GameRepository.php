<?php

declare(strict_types=1);

namespace LeCercle\Game;

use LeCercle\Shared\NotFoundException;

/** Port de stockage des parties : l'agrégat Game entre et sort entier, ses coups compris. */
interface GameRepository
{
    /** Nouvel identifiant : « g » suivi de 12 chiffres hexadécimaux. */
    public function nextId(): string;

    public function add(Game $game): void;

    /** @throws NotFoundException « Partie introuvable. » */
    public function get(string $id): Game;

    /**
     * Comme get(), la ligne restant verrouillée jusqu'à la fin de la transaction.
     *
     * @throws NotFoundException « Partie introuvable. »
     */
    public function getForUpdate(string $id): Game;

    /**
     * État léger de la partie, lu sans ses coups ni verrou.
     *
     * @throws NotFoundException « Partie introuvable. »
     */
    public function pulse(string $id): GamePulse;

    public function save(Game $game): void;

    /** Supprime la partie, ses coups et ses commentaires ; un appariement de tournoi perd son lien. */
    public function delete(string $id): void;

    /**
     * Parties terminées, tous modes, par identifiant ; leurs lignes restent verrouillées jusqu'à la fin de la transaction.
     *
     * @return list<GameSummary>
     */
    public function finished(): array;

    /**
     * Toutes les parties, tous modes, les plus récentes d'abord.
     *
     * @return list<GameSummary>
     */
    public function all(int $limit): array;

    /**
     * Parties en ligne ouvertes ou en cours, plus celles terminées depuis $finishedSince ; les plus récentes d'abord.
     *
     * @return list<GameSummary>
     */
    public function lobby(\DateTimeImmutable $finishedSince, int $limit): array;

    /**
     * Parties non terminées dont $userId est propriétaire ou joueur ; les plus récentes d'abord.
     *
     * @return list<GameSummary>
     */
    public function unfinishedOf(string $userId, int $limit): array;
}
