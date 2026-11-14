<?php

declare(strict_types=1);

namespace LeCercle\Infra\Http\Api;

use LeCercle\Chat\DirectMessageService;
use LeCercle\Infra\Http\ActionRouter;
use LeCercle\Infra\Http\JsonRequest;
use LeCercle\Infra\Http\JsonResponse;

/** Contrat HTTP des messages privés (api/direct-messages.php). */
final class DirectMessagesController
{
    public function __construct(private readonly DirectMessageService $service) {}

    public function routes(): ActionRouter
    {
        return new ActionRouter([
            'open' => fn(JsonRequest $r): JsonResponse => JsonResponse::ok(['peer' => $this->service->open($r->string('pseudo'))]),
            'threads' => fn(JsonRequest $r): JsonResponse => JsonResponse::ok($this->service->threads()),
            'conversation' => fn(JsonRequest $r): JsonResponse => JsonResponse::ok([
                'messages' => $this->service->conversation($r->string('peerId')),
            ]),
            'send' => fn(JsonRequest $r): JsonResponse => JsonResponse::ok([
                'message' => $this->service->send($r->string('peerId'), $r->string('text')),
            ]),
        ]);
    }
}
