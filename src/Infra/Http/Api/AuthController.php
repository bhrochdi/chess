<?php

declare(strict_types=1);

namespace LeCercle\Infra\Http\Api;

use LeCercle\Infra\Http\ActionRouter;
use LeCercle\Infra\Http\JsonRequest;
use LeCercle\Infra\Http\JsonResponse;
use LeCercle\User\AuthService;
use LeCercle\User\PresenceService;
use LeCercle\User\UserAdminService;

/** Actions de api/auth.php ; ssoLogin et recordResult n'existent plus (« Action inconnue. »). */
final class AuthController
{
    public function __construct(
        private readonly AuthService $auth,
        private readonly UserAdminService $admin,
        private readonly PresenceService $presence,
        private readonly string $clientAddress,
    ) {}

    public function routes(): ActionRouter
    {
        return new ActionRouter([
            'register' => fn(JsonRequest $r): JsonResponse => JsonResponse::ok([
                'user' => $this->auth->register($r->string('pseudo'), $r->string('email'), $r->string('password')),
            ]),
            'login' => fn(JsonRequest $r): JsonResponse => JsonResponse::ok([
                'user' => $this->auth->login($r->string('identifiant'), $r->string('password'), $this->clientAddress),
            ]),
            'logout' => function (): JsonResponse {
                $this->auth->logout();

                return JsonResponse::ok();
            },
            'me' => fn(): JsonResponse => JsonResponse::ok(['user' => $this->auth->me()]),
            'guest' => fn(JsonRequest $r): JsonResponse => JsonResponse::ok(['user' => $this->auth->guest($r->optionalString('pseudo'))]),
            'claimAdmin' => fn(): JsonResponse => JsonResponse::ok(['user' => $this->auth->claimAdmin()]),
            'list' => fn(): JsonResponse => JsonResponse::ok(['users' => $this->admin->list()]),
            'adminUpdate' => function (JsonRequest $r): JsonResponse {
                $this->admin->update($r->string('targetId'), $r->string('op'));

                return JsonResponse::ok();
            },
            'purgeGuests' => fn(): JsonResponse => JsonResponse::ok(['deleted' => $this->admin->purgeGuests()]),
            'heartbeat' => function (): JsonResponse {
                $this->presence->heartbeat();

                return JsonResponse::ok();
            },
            'online' => fn(): JsonResponse => JsonResponse::ok(['players' => $this->presence->online()]),
        ]);
    }
}
