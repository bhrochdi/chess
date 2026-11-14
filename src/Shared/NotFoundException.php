<?php

declare(strict_types=1);

namespace LeCercle\Shared;

/** Ressource introuvable : répondue en HTTP 404. Le message est montré à l'utilisateur, en français. */
final class NotFoundException extends \RuntimeException {}
