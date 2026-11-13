<?php

declare(strict_types=1);

namespace LeCercle\Infra\Http;

use JsonException;
use LeCercle\Shared\ValidationException;

/**
 * Corps JSON d'une requête : un objet portant une « action » et ses paramètres,
 * lus avec leur type JSON exact.
 */
final readonly class JsonRequest
{
    /** @param array<mixed> $data */
    private function __construct(private string $action, private array $data) {}

    public static function fromBody(string $body): self
    {
        try {
            $object = json_decode($body, false, 512, JSON_THROW_ON_ERROR);
            $data = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new HttpException(400, 'Corps JSON invalide.');
        }
        if (!$object instanceof \stdClass || !\is_array($data)) {
            throw new HttpException(400, 'Corps JSON invalide.');
        }
        if (!isset($data['action']) || !\is_string($data['action'])) {
            throw new HttpException(400, 'Action inconnue.');
        }

        return new self($data['action'], $data);
    }

    public function action(): string
    {
        return $this->action;
    }

    public function string(string $key): string
    {
        $value = $this->data[$key] ?? null;

        return \is_string($value) ? $value : throw self::invalid($key);
    }

    public function int(string $key): int
    {
        $value = $this->data[$key] ?? null;

        return \is_int($value) ? $value : throw self::invalid($key);
    }

    public function bool(string $key): bool
    {
        $value = $this->data[$key] ?? null;

        return \is_bool($value) ? $value : throw self::invalid($key);
    }

    public function optionalString(string $key): ?string
    {
        $value = $this->data[$key] ?? null;

        return $value === null || \is_string($value) ? $value : throw self::invalid($key);
    }

    public function optionalInt(string $key): ?int
    {
        $value = $this->data[$key] ?? null;

        return $value === null || \is_int($value) ? $value : throw self::invalid($key);
    }

    /** @return array<mixed>|null */
    public function optionalArray(string $key): ?array
    {
        $value = $this->data[$key] ?? null;

        return $value === null || \is_array($value) ? $value : throw self::invalid($key);
    }

    private static function invalid(string $key): ValidationException
    {
        return new ValidationException("Paramètre « $key » manquant ou invalide.");
    }
}
