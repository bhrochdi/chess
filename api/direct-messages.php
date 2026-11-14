<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use LeCercle\Chat\DirectMessageService;
use LeCercle\Chat\MessageViews;
use LeCercle\Infra\Chat\PdoDirectMessageRepository;
use LeCercle\Infra\Http\ActionRouter;
use LeCercle\Infra\Http\Api\DirectMessagesController;
use LeCercle\Infra\Http\HttpKernel;
use LeCercle\Infra\Infrastructure;

// Messages privés : ouverture d'une conversation, fils, lecture, envoi.
HttpKernel::serve(static function (): ActionRouter {
    $infra = Infrastructure::fromGlobals(__DIR__ . '/config.php');

    return (new DirectMessagesController(new DirectMessageService(
        new PdoDirectMessageRepository($infra->pdo()),
        $infra->access(),
        $infra->members(),
        $infra->clock(),
        new MessageViews($infra->members()),
    )))->routes();
});
