<?php

declare(strict_types=1);

namespace LeCercle\Shared;

/** Limite le débit d'une action : compte les événements récents d'une clé (un compte, une adresse). */
interface Throttle
{
    /**
     * Compte un événement pour $key à l'instant $nowMs et répond vrai ; si $max événements ont déjà
     * été comptés dans les $windowMs millisecondes qui précèdent, répond faux sans rien compter.
     * À appeler hors transaction : le décompte doit voir les événements des requêtes simultanées.
     */
    public function allow(string $key, int $max, int $windowMs, int $nowMs): bool;
}
