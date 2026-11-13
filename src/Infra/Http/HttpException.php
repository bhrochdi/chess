<?php

declare(strict_types=1);

namespace LeCercle\Infra\Http;

/** Erreur de protocole HTTP (400, 405) : levée par la couche HTTP, jamais par un service. */
final class HttpException extends \RuntimeException
{
    public function __construct(public readonly int $status, string $message)
    {
        parent::__construct($message);
    }
}
