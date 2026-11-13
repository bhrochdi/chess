<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use LeCercle\Infra\Http\ActionRouter;
use LeCercle\Infra\Http\HttpKernel;
use LeCercle\Infra\Http\JsonRequest;
use LeCercle\Infra\Http\JsonResponse;
use LeCercle\Infra\Infrastructure;

// Contrôle de santé : la pile HTTP et la connexion MySQL répondent.
HttpKernel::serve(static function (): ActionRouter {
    $infra = Infrastructure::fromGlobals(__DIR__ . '/config.php');

    return new ActionRouter([
        'ping' => static function (JsonRequest $request) use ($infra): JsonResponse {
            $infra->pdo()->query('SELECT 1');

            return JsonResponse::ok();
        },
    ]);
});
