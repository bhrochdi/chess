<?php

declare(strict_types=1);

namespace LeCercle\Infra\Http;

/** Associe chaque « action » du corps JSON à son traitement. */
final class ActionRouter
{
    /** @param array<string, \Closure(JsonRequest): JsonResponse> $actions */
    public function __construct(private readonly array $actions) {}

    public function dispatch(JsonRequest $request): JsonResponse
    {
        $action = $this->actions[$request->action()] ?? throw new HttpException(400, 'Action inconnue.');

        return $action($request);
    }
}
