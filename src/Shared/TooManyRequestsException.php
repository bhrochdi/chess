<?php

declare(strict_types=1);

namespace LeCercle\Shared;

/** Trop de tentatives dans la fenêtre : répondu en HTTP 429. Le message est montré à l'utilisateur, en français. */
final class TooManyRequestsException extends \RuntimeException {}
