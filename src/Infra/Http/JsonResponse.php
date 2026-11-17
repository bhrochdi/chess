<?php

declare(strict_types=1);

namespace LeCercle\Infra\Http;

/**
 * Réponse JSON au format de l'API : {"ok": true, …} ou {"ok": false, "error": "…"}.
 * Le JSON est encodé à la construction : une erreur d'encodage est levée dans
 * la zone protégée du noyau, avant tout envoi.
 */
final readonly class JsonResponse
{
    private const FLAGS = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR;

    private function __construct(public int $status, public string $json) {}

    /** @param array<string, mixed> $data */
    public static function ok(array $data = []): self
    {
        return new self(200, json_encode(['ok' => true] + $data, self::FLAGS));
    }

    /** Réponse à une relecture périodique dont le navigateur affiche déjà l'état : {"ok": true, "unchanged": true}. */
    public static function unchanged(): self
    {
        return self::ok(['unchanged' => true]);
    }

    public static function error(int $status, string $message): self
    {
        return new self(
            $status,
            json_encode(['ok' => false, 'error' => $message], self::FLAGS | JSON_INVALID_UTF8_SUBSTITUTE),
        );
    }

    /**
     * En-têtes de chaque réponse de l'API : JSON jamais mis en cache, jamais interprété comme autre chose
     * (nosniff), jamais affiché dans un cadre ni autorisé à charger quoi que ce soit.
     *
     * @return list<string>
     */
    public static function headers(): array
    {
        return [
            'Content-Type: application/json; charset=utf-8',
            'Cache-Control: no-store',
            'X-Content-Type-Options: nosniff',
            "Content-Security-Policy: default-src 'none'; frame-ancestors 'none'",
            'Referrer-Policy: no-referrer',
        ];
    }

    public function send(): void
    {
        http_response_code($this->status);
        foreach (self::headers() as $header) {
            header($header);
        }
        echo $this->json;
    }
}
