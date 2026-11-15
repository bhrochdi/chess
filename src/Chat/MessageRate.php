<?php

declare(strict_types=1);

namespace LeCercle\Chat;

use LeCercle\Shared\Millis;
use LeCercle\Shared\Throttle;
use LeCercle\Shared\TooManyRequestsException;

/** Débit d'envoi d'un compte, tous canaux confondus : salons, messages privés, commentaires de partie. */
final class MessageRate
{
    public const MAX = 8;

    public const WINDOW_MS = 20_000;

    public const TOO_FAST = 'Tu envoies des messages trop vite. Patiente quelques secondes.';

    /** À appeler une fois le message validé, juste avant de l'enregistrer : un message refusé ne compte pas. */
    public static function check(Throttle $throttle, string $authorId, \DateTimeImmutable $now): void
    {
        if (!$throttle->allow('message:' . $authorId, self::MAX, self::WINDOW_MS, Millis::of($now))) {
            throw new TooManyRequestsException(self::TOO_FAST);
        }
    }
}
