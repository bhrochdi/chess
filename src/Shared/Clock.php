<?php

declare(strict_types=1);

namespace LeCercle\Shared;

/** Horloge injectable (équivalent de PSR-20 sans la dépendance) : renvoie toujours l'heure en UTC. */
interface Clock
{
    public function now(): \DateTimeImmutable;
}
