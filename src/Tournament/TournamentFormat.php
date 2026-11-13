<?php

declare(strict_types=1);

namespace LeCercle\Tournament;

/** Format d'un tournoi, tel que stocké par le front. */
enum TournamentFormat: string
{
    case RoundRobin = 'round-robin';
    case Elimination = 'elimination';
    case Swiss = 'suisse';
}
