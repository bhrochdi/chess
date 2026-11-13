<?php

declare(strict_types=1);

namespace LeCercle\Infra\Http\Api;

use LeCercle\Chat\GameCommentService;
use LeCercle\Infra\Http\ActionRouter;
use LeCercle\Infra\Http\JsonRequest;
use LeCercle\Infra\Http\JsonResponse;

/** Contrat HTTP des commentaires de partie (api/game-comments.php). */
final class GameCommentsController
{
    public function __construct(private readonly GameCommentService $service) {}

    public function routes(): ActionRouter
    {
        return new ActionRouter([
            'list' => fn(JsonRequest $r): JsonResponse => JsonResponse::ok([
                'comments' => $this->service->list($r->string('gameId')),
            ]),
            'send' => fn(JsonRequest $r): JsonResponse => JsonResponse::ok([
                'comment' => $this->service->send($r->string('gameId'), $r->string('text')),
            ]),
        ]);
    }
}
