<?php

declare(strict_types=1);

namespace LeCercle\Shared;

/** Port du journal d'administration : le texte de l'action est en français, destiné à l'affichage. */
interface AdminJournal
{
    public function record(string $actorId, string $action): void;
}
