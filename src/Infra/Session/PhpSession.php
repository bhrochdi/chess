<?php

declare(strict_types=1);

namespace LeCercle\Infra\Session;

use LeCercle\Shared\CurrentUser;
use LeCercle\Shared\ForbiddenException;
use LeCercle\Shared\SessionLogin;

/**
 * Session PHP native, clé $_SESSION['user_id'] comme dans api/auth.php (même
 * cookie PHPSESSID). La lecture ouvre la session en read_and_close : les
 * requêtes de polling n'attendent pas le verrou de session.
 */
final class PhpSession implements CurrentUser, SessionLogin
{
    private const KEY = 'user_id';
    private const OPTIONS = [
        'cookie_httponly' => true,
        'cookie_samesite' => 'Lax',
        'use_strict_mode' => true,
    ];

    public function id(): ?string
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            if (!$this->hasSessionId()) {
                return null;
            }
            session_start(self::OPTIONS + ['read_and_close' => true]);
        }
        $id = $_SESSION[self::KEY] ?? null;

        return \is_string($id) && $id !== '' ? $id : null;
    }

    public function requireId(): string
    {
        return $this->id() ?? throw new ForbiddenException('Connexion requise.');
    }

    public function logIn(string $userId): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start(self::OPTIONS);
        }
        session_regenerate_id(true);
        $_SESSION[self::KEY] = $userId;
        session_write_close();
    }

    public function logOut(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            if (!$this->hasSessionId()) {
                return;
            }
            session_start(self::OPTIONS);
        }
        $_SESSION = [];
        session_destroy();
    }

    /** Sans cookie ni identifiant fixé, aucune session à ouvrir (pas de cookie créé pour un anonyme). */
    private function hasSessionId(): bool
    {
        return session_id() !== '' || isset($_COOKIE[session_name()]);
    }
}
