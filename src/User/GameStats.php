<?php

declare(strict_types=1);

namespace LeCercle\User;

/** Victoires, défaites et nulles des parties en ligne terminées, celles que la purge a supprimées comprises. */
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
