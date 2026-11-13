<?php

declare(strict_types=1);

namespace LeCercle\Shared;

/** Entrée invalide : répondue en HTTP 422. Le message est montré à l'utilisateur, en français. */
final class ValidationException extends \InvalidArgumentException {}
