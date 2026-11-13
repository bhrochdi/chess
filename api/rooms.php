<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use LeCercle\Chat\MessageViews;
use LeCercle\Chat\RoomService;
use LeCercle\Infra\Chat\PdoRoomMessageRepository;
use LeCercle\Infra\Chat\PdoRoomRepository;
use LeCercle\Infra\Http\ActionRouter;
use LeCercle\Infra\Http\Api\RoomsController;
use LeCercle\Infra\Http\HttpKernel;
use LeCercle\Infra\Infrastructure;

// Salons : liste, messages, envoi ; gestion et modération par les administrateurs.
HttpKernel::serve(static function (): ActionRouter {
    $infra = Infrastructure::fromGlobals(__DIR__ . '/config.php');

    return (new RoomsController(new RoomService(
        new PdoRoomRepository($infra->pdo()),
        new PdoRoomMessageRepository($infra->pdo()),
        $infra->access(),
        $infra->clock(),
        new MessageViews($infra->members()),
        $infra->adminJournal(),
        $infra->transactions(),
        $infra->members(),
    )))->routes();
});
