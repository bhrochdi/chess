<?php

declare(strict_types=1);

namespace LeCercle\Infra\Http;

/**
 * Noyau des points d'entrée api/*.php : POST uniquement, corps JSON, routage
 * par action ; toute exception passe par l'ExceptionMapper.
 */
final class HttpKernel
{
    public function __construct(private readonly ExceptionMapper $errors) {}

    /** @param \Closure(): ActionRouter $routes construites dans la zone protégée (base indisponible → 500 JSON) */
    public function handle(string $method, string $body, \Closure $routes): JsonResponse
    {
        try {
            if ($method !== 'POST') {
                throw new HttpException(405, 'Méthode non autorisée : POST requis.');
            }
            $request = JsonRequest::fromBody($body);

            return $routes()->dispatch($request);
        } catch (\Throwable $e) {
            return $this->errors->toResponse($e);
        }
    }

    /** @param \Closure(): ActionRouter $routes */
    public static function serve(\Closure $routes): void
    {
        ini_set('display_errors', '0');
        set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
            if ((error_reporting() & $severity) === 0) {
                return false;
            }

            throw new \ErrorException($message, 0, $severity, $file, $line);
        });

        $method = $_SERVER['REQUEST_METHOD'] ?? '';
        $kernel = new self(new ExceptionMapper(static function (string $line): void {
            error_log($line);
        }));
        $kernel->handle(\is_string($method) ? $method : '', file_get_contents('php://input') ?: '', $routes)->send();
    }
}
