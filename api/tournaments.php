<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use LeCercle\Infra\Http\ActionRouter;
use LeCercle\Infra\Http\Api\TournamentsController;
use LeCercle\Infra\Http\HttpKernel;
use LeCercle\Infra\Infrastructure;
use LeCercle\Infra\Link\GameTournamentServices;

// Tournois : lecture publique, inscriptions des membres, administration journalisée, une partie par appariement.
HttpKernel::serve(static function (): ActionRouter {
    $services = new GameTournamentServices(Infrastructure::fromGlobals(__DIR__ . '/config.php'));

    return (new TournamentsController($services->tournaments()))->routes();
});
