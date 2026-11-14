<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use LeCercle\Infra\Http\ActionRouter;
use LeCercle\Infra\Http\Api\GamesController;
use LeCercle\Infra\Http\HttpKernel;
use LeCercle\Infra\Infrastructure;
use LeCercle\Infra\Link\GameTournamentServices;

// Parties : création, coups, abandon, nulle, pendule, PGN, administration.
HttpKernel::serve(static function (): ActionRouter {
    $services = new GameTournamentServices(Infrastructure::fromGlobals(__DIR__ . '/config.php'));

    return (new GamesController($services->games()))->routes();
});
