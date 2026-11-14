<?php

declare(strict_types=1);

namespace LeCercle\Infra\Http\Api;

use LeCercle\Chat\RoomService;
use LeCercle\Infra\Http\ActionRouter;
use LeCercle\Infra\Http\JsonRequest;
use LeCercle\Infra\Http\JsonResponse;

/** Contrat HTTP des salons (api/rooms.php). */
final class RoomsController
{
    public function __construct(private readonly RoomService $service) {}

    public function routes(): ActionRouter
    {
        return new ActionRouter([
            'list' => fn(JsonRequest $r): JsonResponse => JsonResponse::ok(['rooms' => $this->service->list()]),
            'listAll' => fn(JsonRequest $r): JsonResponse => JsonResponse::ok(['rooms' => $this->service->listAll()]),
            'messages' => fn(JsonRequest $r): JsonResponse => JsonResponse::ok([
                'messages' => $this->service->messages($r->string('roomId')),
            ]),
            'send' => fn(JsonRequest $r): JsonResponse => JsonResponse::ok([
                'message' => $this->service->send($r->string('roomId'), $r->string('text')),
            ]),
            'create' => fn(JsonRequest $r): JsonResponse => JsonResponse::ok([
                'room' => $this->service->create($r->string('name'), $r->optionalString('description') ?? ''),
            ]),
            'rename' => fn(JsonRequest $r): JsonResponse => JsonResponse::ok([
                'room' => $this->service->rename($r->string('roomId'), $r->string('name')),
            ]),
            'archive' => fn(JsonRequest $r): JsonResponse => JsonResponse::ok([
                'room' => $this->service->setArchived($r->string('roomId'), $r->bool('archived')),
            ]),
            'move' => fn(JsonRequest $r): JsonResponse => JsonResponse::ok([
                'rooms' => $this->service->move($r->string('roomId'), $r->string('direction')),
            ]),
            'delete' => function (JsonRequest $r): JsonResponse {
                $this->service->delete($r->string('roomId'));

                return JsonResponse::ok();
            },
            'deleteMessage' => function (JsonRequest $r): JsonResponse {
                $this->service->deleteMessage($r->string('roomId'), $r->int('messageId'));

                return JsonResponse::ok();
            },
            'clear' => function (JsonRequest $r): JsonResponse {
                $this->service->clear($r->string('roomId'));

                return JsonResponse::ok();
            },
        ]);
    }
}
