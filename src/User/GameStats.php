<?php

declare(strict_types=1);

namespace LeCercle\User;

/** Victoires, défaites et nulles calculées depuis les parties en ligne terminées. */
interface GameStats
{
    /**
     * Les comptes sans partie terminée sont absents du résultat.
     *
     * @param list<string> $userIds
     *
     * @return array<string, Stats>
     */
    public function forUsers(array $userIds): array;
}
