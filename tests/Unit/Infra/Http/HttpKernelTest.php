<?php

declare(strict_types=1);

namespace LeCercle\Tests\Unit\Infra\Http;

use LeCercle\Infra\Http\ActionRouter;
use LeCercle\Infra\Http\ExceptionMapper;
use LeCercle\Infra\Http\HttpKernel;
use LeCercle\Infra\Http\JsonRequest;
use LeCercle\Infra\Http\JsonResponse;
use LeCercle\Shared\NotFoundException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class HttpKernelTest extends TestCase
{
    /** @var list<string> */
    private array $logged = [];

    private function kernel(): HttpKernel
    {
        return new HttpKernel(new ExceptionMapper(function (string $line): void {
            $this->logged[] = $line;
        }));
    }

    /** @return \Closure(): ActionRouter */
    private static function routes(): \Closure
    {
        return static fn(): ActionRouter => new ActionRouter([
            'ping' => static fn(JsonRequest $r): JsonResponse => JsonResponse::ok(),
            'lost' => static fn(JsonRequest $r): JsonResponse => throw new NotFoundException('Partie introuvable.'),
        ]);
    }

    public function testPostIsDispatched(): void
    {
        $response = $this->kernel()->handle('POST', '{"action":"ping"}', self::routes());

        self::assertSame(200, $response->status);
        self::assertSame('{"ok":true}', $response->json);
    }

    public function testGetIs405(): void
    {
        $response = $this->kernel()->handle('GET', '', self::routes());

        self::assertSame(405, $response->status);
        self::assertSame('{"ok":false,"error":"Méthode non autorisée : POST requis."}', $response->json);
    }

    /** @return iterable<string, array{string}> */
    public static function malformedBodies(): iterable
    {
        yield 'vide' => [''];
        yield 'tronqué' => ['{'];
        yield 'liste' => ['[1]'];
        yield 'chaîne' => ['"x"'];
        yield 'action non chaîne' => ['{"action":1}'];
        yield 'sans action' => ['{}'];
        yield 'UTF-8 invalide' => ["{\"action\":\"\xff\"}"];
        yield 'action inconnue' => ['{"action":"nope"}'];
    }

    #[DataProvider('malformedBodies')]
    public function testMalformedBodyGives400(string $body): void
    {
        $response = $this->kernel()->handle('POST', $body, self::routes());

        self::assertSame(400, $response->status);
        $data = json_decode($response->json, true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($data);
        self::assertFalse($data['ok']);
        self::assertSame([], $this->logged);
    }

    public function testRoutesAreBuiltInsideTheErrorBoundary(): void
    {
        $response = $this->kernel()->handle(
            'POST',
            '{"action":"ping"}',
            static fn(): ActionRouter => throw new \RuntimeException('base indisponible'),
        );

        self::assertSame(500, $response->status);
        self::assertCount(1, $this->logged);
        self::assertStringContainsString('base indisponible', $this->logged[0]);
    }

    public function testServiceExceptionsAreMapped(): void
    {
        $response = $this->kernel()->handle('POST', '{"action":"lost"}', self::routes());

        self::assertSame(404, $response->status);
        self::assertSame('{"ok":false,"error":"Partie introuvable."}', $response->json);
    }
}
