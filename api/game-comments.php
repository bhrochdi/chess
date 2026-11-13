<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use LeCercle\Chat\GameCommentService;
use LeCercle\Chat\MessageViews;
use LeCercle\Infra\Chat\PdoCommentableGames;
use LeCercle\Infra\Chat\PdoGameCommentRepository;
use LeCercle\Infra\Http\ActionRouter;
use LeCercle\Infra\Http\Api\GameCommentsController;
use LeCercle\Infra\Http\HttpKernel;
use LeCercle\Infra\Infrastructure;

// Commentaires en direct sur une partie : lecture publique, envoi connecté.
HttpKernel::serve(static function (): ActionRouter {
    $infra = Infrastructure::fromGlobals(__DIR__ . '/config.php');

    return (new GameCommentsController(new GameCommentService(
        new PdoGameCommentRepository($infra->pdo()),
        new PdoCommentableGames($infra->pdo()),
        $infra->access(),
        $infra->clock(),
        new MessageViews($infra->members()),
    )))->routes();
});
