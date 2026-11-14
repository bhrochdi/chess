<?php

declare(strict_types=1);

namespace LeCercle\Infra\Http\Api;

use LeCercle\Infra\Http\ActionRouter;
use LeCercle\Infra\Http\JsonRequest;
use LeCercle\Infra\Http\JsonResponse;
use LeCercle\Tournament\TournamentService;

/** Contrat HTTP des tournois (api/tournaments.php). */
final class TournamentsController
{
    public function __construct(private readonly TournamentService $service) {}

    public function routes(): ActionRouter
    {
        return new ActionRouter([
            'list' => fn(JsonRequest $r): JsonResponse => JsonResponse::ok(['tournaments' => $this->service->list()]),
            'get' => fn(JsonRequest $r): JsonResponse => $this->tournament($this->service->get($r->string('id'))),
            'register' => fn(JsonRequest $r): JsonResponse => $this->tournament($this->service->register($r->string('id'))),
            'unregister' => fn(JsonRequest $r): JsonResponse => $this->tournament($this->service->unregister($r->string('id'))),
            'create' => fn(JsonRequest $r): JsonResponse => $this->tournament($this->service->create(
                $r->string('name'),
                $r->optionalString('description') ?? '',
                $r->string('format'),
                $r->optionalArray('tc'),
                $r->optionalInt('rounds'),
            )),
            'setRegistration' => fn(JsonRequest $r): JsonResponse => $this->tournament(
                $this->service->setRegistration($r->string('id'), $r->bool('open')),
            ),
            'start' => fn(JsonRequest $r): JsonResponse => $this->tournament($this->service->start($r->string('id'))),
            'setResult' => fn(JsonRequest $r): JsonResponse => $this->tournament($this->service->setResult(
                $r->string('id'),
                $r->int('round'),
                $r->int('board'),
                $r->optionalString('result'),
            )),
            'nextRound' => fn(JsonRequest $r): JsonResponse => $this->tournament(
                $this->service->nextRound($r->string('id'), $r->bool('force')),
            ),
            'close' => fn(JsonRequest $r): JsonResponse => $this->tournament($this->service->close($r->string('id'))),
            'delete' => function (JsonRequest $r): JsonResponse {
                $this->service->delete($r->string('id'));

                return JsonResponse::ok();
            },
        ]);
    }

    /** @param array<string, mixed> $view */
    private function tournament(array $view): JsonResponse
    {
        return JsonResponse::ok(['tournament' => $view]);
    }
}
