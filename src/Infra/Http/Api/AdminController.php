<?php

declare(strict_types=1);

namespace LeCercle\Infra\Http\Api;

use LeCercle\Admin\AdminService;
use LeCercle\Infra\Http\ActionRouter;
use LeCercle\Infra\Http\JsonResponse;

/** Contrat HTTP de l'administration (api/admin.php). */
final class AdminController
{
    public function __construct(private readonly AdminService $admin) {}

    public function routes(): ActionRouter
    {
        return new ActionRouter([
            'journal' => fn(): JsonResponse => JsonResponse::ok(['entries' => $this->admin->journal()]),
            'dashboard' => fn(): JsonResponse => JsonResponse::ok(['counts' => $this->admin->dashboard()]),
        ]);
    }
}
