<?php

declare(strict_types=1);

namespace LeCercle\Shared;

/** Conflit avec l'état actuel : répondu en HTTP 409. Le message est montré à l'utilisateur, en français. */
final class ConflictException extends \RuntimeException {}
