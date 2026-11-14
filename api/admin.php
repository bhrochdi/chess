<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use LeCercle\Admin\AdminService;
use LeCercle\Infra\Admin\PdoDashboardCounts;
use LeCercle\Infra\Admin\PdoJournalReader;
use LeCercle\Infra\Http\ActionRouter;
use LeCercle\Infra\Http\Api\AdminController;
use LeCercle\Infra\Http\HttpKernel;
use LeCercle\Infra\Infrastructure;

// Administration : journal et tableau de bord, en lecture seule.
HttpKernel::serve(static function (): ActionRouter {
    $infra = Infrastructure::fromGlobals(__DIR__ . '/config.php');

    return (new AdminController(new AdminService(
        $infra->access(),
        $infra->members(),
        $infra->clock(),
        new PdoJournalReader($infra->pdo()),
        new PdoDashboardCounts($infra->pdo()),
    )))->routes();
});
