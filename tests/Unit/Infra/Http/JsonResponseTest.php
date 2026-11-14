<?php

declare(strict_types=1);

namespace LeCercle\Tests\Unit\Infra\Http;

use JsonException;
use LeCercle\Infra\Http\JsonResponse;
use PHPUnit\Framework\TestCase;

final class JsonResponseTest extends TestCase
{
    public function testOkMergesTheDataWithoutEscapingUnicodeOrSlashes(): void
    {
        $response = JsonResponse::ok(['user' => ['pseudo' => 'Éloïse/1']]);

        self::assertSame('{"ok":true,"user":{"pseudo":"Éloïse/1"}}', $response->json);
        self::assertSame(200, $response->status);
    }

    public function testOkWithoutData(): void
    {
        self::assertSame('{"ok":true}', JsonResponse::ok()->json);
    }

    public function testError(): void
    {
        $response = JsonResponse::error(422, 'Coup illégal.');

        self::assertSame('{"ok":false,"error":"Coup illégal."}', $response->json);
        self::assertSame(422, $response->status);
    }

    public function testErrorNeverThrowsOnInvalidUtf8(): void
    {
        self::assertSame("{\"ok\":false,\"error\":\"a\u{FFFD}\"}", JsonResponse::error(500, "a\xff")->json);
    }

    public function testInvalidUtf8DataThrows(): void
    {
        $this->expectException(JsonException::class);
        JsonResponse::ok(['x' => "\xff"]);
    }
}
