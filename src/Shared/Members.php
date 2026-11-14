<?php

declare(strict_types=1);

namespace LeCercle\Shared;

/** Lecture des comptes, pour les services qui ne dépendent pas du module utilisateurs. */
interface Members
{
    public function find(string $id): ?Member;

    /** Recherche insensible à la casse. */
    public function findByPseudo(string $pseudo): ?Member;

    /**
     * @param array<int, string> $ids
     *
     * @return array<string, Member> indexés par identifiant ; les identifiants inconnus sont absents
     */
    public function findMany(array $ids): array;
}
