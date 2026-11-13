<?php

declare(strict_types=1);

namespace LeCercle\Infra\Http\Api;

use LeCercle\Game\GameService;
use LeCercle\Infra\Http\ActionRouter;
use LeCercle\Infra\Http\JsonRequest;
use LeCercle\Infra\Http\JsonResponse;

/** Contrat HTTP des parties (api/games.php). */
final class GamesController
{
    public function __construct(private readonly GameService $games) {}

    public function routes(): ActionRouter
    {
        return new ActionRouter([
            'create' => fn(JsonRequest $r): JsonResponse => $this->game($this->games->create(
                $r->string('mode'),
                $r->optionalString('level'),
                $r->optionalArray('tc'),
                $r->optionalString('color'),
            )),
            'join' => fn(JsonRequest $r): JsonResponse => $this->game($this->games->join($r->string('id'))),
            'get' => fn(JsonRequest $r): JsonResponse => $this->game($this->games->get($r->string('id'))),
            'move' => fn(JsonRequest $r): JsonResponse => $this->game(
                $this->games->move($r->string('id'), $r->string('uci'), $r->int('ply')),
            ),
            'resign' => fn(JsonRequest $r): JsonResponse => $this->game($this->games->resign($r->string('id'))),
            'offerDraw' => fn(JsonRequest $r): JsonResponse => $this->game($this->games->offerDraw($r->string('id'))),
            'acceptDraw' => fn(JsonRequest $r): JsonResponse => $this->game($this->games->acceptDraw($r->string('id'))),
            'undo' => fn(JsonRequest $r): JsonResponse => $this->game($this->games->undo($r->string('id'))),
            'list' => fn(): JsonResponse => JsonResponse::ok(['games' => $this->games->lobby()]),
            'mine' => fn(): JsonResponse => JsonResponse::ok(['games' => $this->games->mine()]),
            'pgn' => fn(JsonRequest $r): JsonResponse => JsonResponse::ok($this->games->pgn($r->string('id'))),
            'adminList' => fn(): JsonResponse => JsonResponse::ok(['games' => $this->games->adminList()]),
            'adminClose' => fn(JsonRequest $r): JsonResponse => $this->game(
                $this->games->adminClose($r->string('id'), $r->optionalString('result')),
            ),
            'adminDelete' => function (JsonRequest $r): JsonResponse {
                $this->games->adminDelete($r->string('id'));

                return JsonResponse::ok();
            },
            'purge' => fn(): JsonResponse => JsonResponse::ok(['deleted' => $this->games->purgeFinished()]),
        ]);
    }

    /** @param array<string, mixed> $view */
    private function game(array $view): JsonResponse
    {
        return JsonResponse::ok(['game' => $view]);
    }
}
