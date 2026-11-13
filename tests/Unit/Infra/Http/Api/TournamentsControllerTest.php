<?php

declare(strict_types=1);

namespace LeCercle\Tests\Unit\Infra\Http\Api;

use LeCercle\Infra\Http\Api\TournamentsController;
use LeCercle\Infra\Http\ExceptionMapper;
use LeCercle\Infra\Http\HttpKernel;
use LeCercle\Infra\Http\JsonResponse;
use LeCercle\Tests\Unit\Tournament\TournamentServiceFixture as F;
use PHPUnit\Framework\TestCase;

final class TournamentsControllerTest extends TestCase
{
    private F $fixture;

    public function testListIsPublicAndEncodesAnEmptyListAsAnArray(): void
    {
        [$status, $body] = $this->call('{"action":"list"}');

        self::assertSame(200, $status);
        self::assertSame([], $body['tournaments']);
        self::assertStringContainsString('"tournaments":[]', $this->raw('{"action":"list"}'));
    }

    public function testListExposesCreatedTournaments(): void
    {
        $this->fixture->tournament('Open');

        [, $body] = $this->call('{"action":"list"}');

        self::assertSame(['Open'], array_column($body['tournaments'], 'name'));
    }

    public function testCreateIsRefusedToANonAdmin(): void
    {
        [$status, $body] = $this->call('{"action":"create","name":"Open","format":"round-robin","tc":null}', F::ALICE);

        self::assertSame(403, $status);
        self::assertSame('Action réservée aux administrateurs.', $body['error']);
    }

    public function testCreateAsAdminOpensRegistrations(): void
    {
        [$status, $body] = $this->call('{"action":"create","name":"Open","format":"round-robin","tc":{"base":600,"inc":0}}', F::ROOT);

        self::assertSame(200, $status);
        self::assertSame('inscriptions', $body['tournament']['status']);
    }

    public function testSetResultRequiresAnIntegerRound(): void
    {
        [$status, $body] = $this->call('{"action":"setResult","id":"t000000000001","round":"1","board":1,"result":"1-0"}', F::ROOT);

        self::assertSame(422, $status);
        self::assertSame('Paramètre « round » manquant ou invalide.', $body['error']);
    }

    public function testNextRoundRequiresAnExplicitForce(): void
    {
        [$status, $body] = $this->call('{"action":"nextRound","id":"t000000000001"}', F::ROOT);

        self::assertSame(422, $status);
        self::assertSame('Paramètre « force » manquant ou invalide.', $body['error']);
    }

    public function testGetUnknownTournamentIs404(): void
    {
        [$status, $body] = $this->call('{"action":"get","id":"t000000000000"}');

        self::assertSame(404, $status);
        self::assertSame('Tournoi introuvable.', $body['error']);
    }

    public function testStartWithOnePlayerIs409(): void
    {
        $id = $this->fixture->tournament('Open', 'round-robin', [F::ALICE]);

        [$status, $body] = $this->call('{"action":"start","id":"' . $id . '"}', F::ROOT);

        self::assertSame(409, $status);
        self::assertSame('Au moins 2 joueurs inscrits sont nécessaires.', $body['error']);
    }

    public function testAGuestCannotRegister(): void
    {
        $id = $this->fixture->tournament();

        [$status, $body] = $this->call('{"action":"register","id":"' . $id . '"}', F::GUEST);

        self::assertSame(403, $status);
        self::assertSame('Un compte invité ne peut pas s\'inscrire à un tournoi.', $body['error']);
    }

    public function testRegisterAndUnregisterAnswerWithTheTournament(): void
    {
        $id = $this->fixture->tournament();

        [$status, $body] = $this->call('{"action":"register","id":"' . $id . '"}', F::ALICE);
        self::assertSame(200, $status);
        self::assertSame($id, $body['tournament']['id']);

        [$status, $body] = $this->call('{"action":"unregister","id":"' . $id . '"}', F::ALICE);
        self::assertSame(200, $status);
        self::assertSame($id, $body['tournament']['id']);
    }

    public function testSetRegistrationRequiresARealBoolean(): void
    {
        [$status, $body] = $this->call('{"action":"setRegistration","id":"t000000000001","open":"no"}', F::ROOT);

        self::assertSame(422, $status);
        self::assertSame('Paramètre « open » manquant ou invalide.', $body['error']);
    }

    public function testSetResultOnAPastRoundIs409(): void
    {
        $id = $this->fixture->started('Open', 'round-robin', [F::ALICE, F::BOB, F::CAROL, F::DAVE]);
        $this->call('{"action":"nextRound","id":"' . $id . '","force":true}', F::ROOT);

        [$status, $body] = $this->call('{"action":"setResult","id":"' . $id . '","round":1,"board":1,"result":"1-0"}', F::ROOT);

        self::assertSame(409, $status);
        self::assertSame('Seuls les résultats de la ronde en cours peuvent être modifiés.', $body['error']);
    }

    public function testSetResultOnTheCurrentRoundAnswersWithTheTournament(): void
    {
        $id = $this->fixture->started('Open', 'round-robin', [F::ALICE, F::BOB]);

        [$status, $body] = $this->call('{"action":"setResult","id":"' . $id . '","round":1,"board":1,"result":"1-0"}', F::ROOT);

        self::assertSame(200, $status);
        self::assertSame($id, $body['tournament']['id']);
    }

    public function testCloseAndDeleteWork(): void
    {
        $id = $this->fixture->started();

        [$status, $body] = $this->call('{"action":"close","id":"' . $id . '"}', F::ROOT);
        self::assertSame(200, $status);
        self::assertSame($id, $body['tournament']['id']);

        [$status, $body] = $this->call('{"action":"delete","id":"' . $id . '"}', F::ROOT);
        self::assertSame([200, ['ok' => true]], [$status, $body]);
    }

    public function testUnknownActionIs400(): void
    {
        [$status] = $this->call('{"action":"nope"}');

        self::assertSame(400, $status);
    }

    protected function setUp(): void
    {
        $this->fixture = new F();
    }

    /** @return array{int, array<string, mixed>} */
    private function call(string $json, ?string $userId = null): array
    {
        $response = $this->respond($json, $userId);

        return [$response->status, json_decode($response->json, true, 512, JSON_THROW_ON_ERROR)];
    }

    private function raw(string $json, ?string $userId = null): string
    {
        return $this->respond($json, $userId)->json;
    }

    private function respond(string $json, ?string $userId): JsonResponse
    {
        $service = $this->fixture->as($userId);

        return (new HttpKernel(new ExceptionMapper(fn() => null)))
            ->handle('POST', $json, fn() => (new TournamentsController($service))->routes());
    }
}
