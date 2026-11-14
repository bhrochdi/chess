<?php

declare(strict_types=1);

namespace LeCercle\Tests\Unit\Infra\Http;

use LeCercle\Infra\Http\ActionRouter;
use LeCercle\Infra\Http\HttpException;
use LeCercle\Infra\Http\JsonRequest;
use LeCercle\Infra\Http\JsonResponse;
use PHPUnit\Framework\TestCase;

final class ActionRouterTest extends TestCase
{
    private function router(): ActionRouter
    {
        return new ActionRouter([
            'ping' => static fn(JsonRequest $r): JsonResponse => JsonResponse::ok(['pong' => $r->action()]),
        ]);
    }

    public function testDispatchesToTheActionClosure(): void
    {
        $response = $this->router()->dispatch(JsonRequest::fromBody('{"action":"ping"}'));

        self::assertSame('{"ok":true,"pong":"ping"}', $response->json);
    }

    public function testUnknownActionIs400(): void
    {
        try {
            $this->router()->dispatch(JsonRequest::fromBody('{"action":"nope"}'));
            self::fail('HttpException attendue.');
        } catch (HttpException $e) {
            self::assertSame(400, $e->status);
            self::assertSame('Action inconnue.', $e->getMessage());
        }
    }
}
