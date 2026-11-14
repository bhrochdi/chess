<?php

declare(strict_types=1);

namespace LeCercle\Infra\Http;

use LeCercle\Shared\ConflictException;
use LeCercle\Shared\ForbiddenException;
use LeCercle\Shared\NotFoundException;
use LeCercle\Shared\TooManyRequestsException;
use LeCercle\Shared\ValidationException;

/**
 * Traduit une exception en réponse HTTP. Seuls les messages des exceptions
 * métier et de protocole sont montrés ; toute autre exception devient une
 * erreur 500 générique et est journalisée.
 */
final class ExceptionMapper
{
    /** @param \Closure(string): void $log */
    public function __construct(private readonly \Closure $log) {}

    public function toResponse(\Throwable $e): JsonResponse
    {
        return match (true) {
            $e instanceof HttpException => JsonResponse::error($e->status, $e->getMessage()),
            $e instanceof ValidationException => JsonResponse::error(422, $e->getMessage()),
            $e instanceof \InvalidArgumentException => JsonResponse::error(422, 'Requête invalide.'),
            $e instanceof NotFoundException => JsonResponse::error(404, $e->getMessage()),
            $e instanceof ForbiddenException => JsonResponse::error(403, $e->getMessage()),
            $e instanceof ConflictException => JsonResponse::error(409, $e->getMessage()),
            $e instanceof TooManyRequestsException => JsonResponse::error(429, $e->getMessage()),
            default => $this->unexpected($e),
        };
    }

    private function unexpected(\Throwable $e): JsonResponse
    {
        ($this->log)(\sprintf(
            '[le-cercle] %s: %s (%s:%d)',
            $e::class,
            $e->getMessage(),
            $e->getFile(),
            $e->getLine(),
        ));

        return JsonResponse::error(500, 'Erreur interne du serveur.');
    }
}
