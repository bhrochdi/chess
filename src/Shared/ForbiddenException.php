<?php

declare(strict_types=1);

namespace LeCercle\Shared;

/** Action interdite : répondue en HTTP 403. Le message est montré à l'utilisateur, en français. */
final class ForbiddenException extends \RuntimeException {}
