<?php

declare(strict_types=1);

namespace LeCercle\Tournament;

/** Étape de vie d'un tournoi. */
enum TournamentStatus: string
{
    case Registration = 'inscriptions';
    case Running = 'en-cours';
    case Finished = 'termine';
}
