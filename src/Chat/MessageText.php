<?php

declare(strict_types=1);

namespace LeCercle\Chat;

use LeCercle\Shared\ValidationException;

/** Règles du texte d'un message (salons, messages privés, commentaires de partie). */
final class MessageText
{
    public const MAX = 500;

    public static function clean(string $text): string
    {
        $text = trim($text);
        if ($text === '') {
            throw new ValidationException('Message vide.');
        }
        if (mb_strlen($text) > self::MAX) {
            throw new ValidationException('Message trop long (' . self::MAX . ' caractères maximum).');
        }

        return $text;
    }
}
