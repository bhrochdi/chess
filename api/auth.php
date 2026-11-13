<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use LeCercle\Infra\Http\ActionRouter;
use LeCercle\Infra\Http\Api\AuthController;
use LeCercle\Infra\Http\HttpKernel;
use LeCercle\Infra\Infrastructure;
use LeCercle\Infra\User\PdoUserRepository;
use LeCercle\Infra\User\PhpPasswordHasher;
use LeCercle\Infra\User\PdoGameStats;
use LeCercle\Infra\User\PdoLoginAttempts;
use LeCercle\User\AuthService;
use LeCercle\User\PresenceService;
use LeCercle\User\UserAdminService;
use LeCercle\User\UserViews;

// Comptes et session : inscription, connexion, invités, administration des comptes, présence.
HttpKernel::serve(static function (): ActionRouter {
    $infra = Infrastructure::fromGlobals(__DIR__ . '/config.php');
    $users = new PdoUserRepository($infra->pdo());
    $views = new UserViews(new PdoGameStats($infra->pdo()));

    return (new AuthController(
        new AuthService($users, new PhpPasswordHasher(), $infra->session(), $infra->access(), $infra->clock(), $infra->transactions(), $views, new PdoLoginAttempts($infra->pdo())),
        new UserAdminService($users, $infra->access(), $infra->adminJournal(), $infra->transactions(), $views, $infra->clock()),
        new PresenceService($users, $infra->access(), $infra->clock()),
        // Adresse vue par Apache ; aucun mandataire n'est placé devant l'application.
        \is_string($_SERVER['REMOTE_ADDR'] ?? null) ? $_SERVER['REMOTE_ADDR'] : '',
    ))->routes();
});
