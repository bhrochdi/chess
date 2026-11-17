<?php

declare(strict_types=1);

namespace LeCercle\Tests\Unit\Infra\Http\Api;

use LeCercle\Infra\Http\Api\GamesController;
use LeCercle\Infra\Http\ExceptionMapper;
use LeCercle\Infra\Http\HttpKernel;
use LeCercle\Infra\Http\JsonResponse;
use LeCercle\Tests\Unit\Game\GameServiceFixture;
use PHPUnit\Framework\TestCase;

final class GamesControllerTest extends TestCase
{
    use GameServiceFixture;

    protected function setUp(): void
    {
        $this->setUpFixture();
    }

    public function testCreateLocalAnswersWithTheGameInContractOrder(): void
    {
        [$status, $body] = $this->call('{"action":"create","mode":"local"}', self::ALICE);

        self::assertSame(200, $status);
        self::assertTrue($body['ok']);
        self::assertSame('local', $body['game']['mode']);
        self::assertSame(
            ['id', 'mode', 'status', 'level', 'white', 'black', 'tc', 'fen', 'turn', 'moves', 'lastMove', 'legalMoves',
                'inCheck', 'captured', 'clock', 'drawOffer', 'result', 'reason', 'yourColor', 'createdAt', 'updatedAt', 'version'],
            array_keys($body['game']),
        );
    }

    public function testCreateNeedsAnAccount(): void
    {
        $response = $this->respond('{"action":"create","mode":"local"}', null);

        self::assertSame(403, $response->status);
        self::assertSame('{"ok":false,"error":"Connexion requise."}', $response->json);
    }

    public function testMoveWithoutPlyIsInvalid(): void
    {
        $id = $this->call('{"action":"create","mode":"local"}', self::ALICE)[1]['game']['id'];

        [$status, $body] = $this->call('{"action":"move","id":"' . $id . '","uci":"e2e4"}', self::ALICE);

        self::assertSame(422, $status);
        self::assertSame('Paramètre « ply » manquant ou invalide.', $body['error']);
    }

    public function testIllegalMoveIsRefused(): void
    {
        $id = $this->call('{"action":"create","mode":"local"}', self::ALICE)[1]['game']['id'];

        [$status, $body] = $this->call('{"action":"move","id":"' . $id . '","uci":"e2e5","ply":0}', self::ALICE);

        self::assertSame(422, $status);
        self::assertSame('Coup illégal.', $body['error']);
    }

    public function testMoveAnswersWithTheGame(): void
    {
        $id = $this->call('{"action":"create","mode":"local"}', self::ALICE)[1]['game']['id'];

        [$status, $body] = $this->call('{"action":"move","id":"' . $id . '","uci":"e2e4","ply":0}', self::ALICE);

        self::assertSame(200, $status);
        self::assertSame(['e4'], $body['game']['moves']);
    }

    public function testUnknownGameIsNotFound(): void
    {
        [$status, $body] = $this->call('{"action":"get","id":"g999999999999"}', self::ALICE);

        self::assertSame(404, $status);
        self::assertSame('Partie introuvable.', $body['error']);
    }

    public function testCancelAnswersOkThenTheGameIsGone(): void
    {
        $id = $this->call('{"action":"create","mode":"online","color":"w"}', self::ALICE)[1]['game']['id'];

        self::assertSame([403, 'Seul l’auteur du défi peut l’annuler.'], $this->statusAndError('{"action":"cancel","id":"' . $id . '"}', self::BOB));
        self::assertSame([200, ['ok' => true]], $this->call('{"action":"cancel","id":"' . $id . '"}', self::ALICE));
        self::assertSame([404, 'Partie introuvable.'], $this->statusAndError('{"action":"get","id":"' . $id . '"}', self::ALICE));
    }

    /** @return array{int, mixed} */
    private function statusAndError(string $json, ?string $userId): array
    {
        [$status, $body] = $this->call($json, $userId);

        return [$status, $body['error'] ?? null];
    }

    public function testJoiningAStartedGameIsAConflict(): void
    {
        $id = $this->onlineGame($this->service());

        [$status, $body] = $this->call('{"action":"join","id":"' . $id . '"}', self::CAROL);

        self::assertSame(409, $status);
        self::assertSame('Cette partie a déjà commencé.', $body['error']);
    }

    public function testLobbyIsPublicAndEmptyListsEncodeAsArrays(): void
    {
        $response = $this->respond('{"action":"list"}', null);

        self::assertSame(200, $response->status);
        self::assertSame('{"ok":true,"games":[]}', $response->json);
    }

    public function testMineListsTheCallersGames(): void
    {
        $id = $this->call('{"action":"create","mode":"local"}', self::ALICE)[1]['game']['id'];

        [$status, $body] = $this->call('{"action":"mine"}', self::ALICE);

        self::assertSame(200, $status);
        self::assertSame([$id], array_column($body['games'], 'id'));
    }

    public function testPgnAnswersWithFilenameAndText(): void
    {
        $id = $this->call('{"action":"create","mode":"local"}', self::ALICE)[1]['game']['id'];

        [$status, $body] = $this->call('{"action":"pgn","id":"' . $id . '"}', self::ALICE);

        self::assertSame(200, $status);
        self::assertSame(['ok', 'filename', 'pgn'], array_keys($body));
    }

    public function testAdminCloseIsForAdminsOnly(): void
    {
        $id = $this->onlineGame($this->service());

        [$status, $body] = $this->call('{"action":"adminClose","id":"' . $id . '"}', self::ALICE);
        self::assertSame(403, $status);
        self::assertSame('Action réservée aux administrateurs.', $body['error']);

        [$status, $body] = $this->call('{"action":"adminClose","id":"' . $id . '","result":"0-1"}', self::ROOT);
        self::assertSame(200, $status);
        self::assertSame(['0-1', 'admin'], [$body['game']['result'], $body['game']['reason']]);
    }

    public function testAdminDeleteAndPurgeAnswer(): void
    {
        $first = $this->onlineGame($this->service());
        $second = $this->onlineGame($this->service());
        $this->call('{"action":"adminClose","id":"' . $second . '"}', self::ROOT);

        self::assertSame('{"ok":true}', $this->respond('{"action":"adminDelete","id":"' . $first . '"}', self::ROOT)->json);
        self::assertSame('{"ok":true,"deleted":1}', $this->respond('{"action":"purge"}', self::ROOT)->json);
        self::assertSame('{"ok":true,"games":[]}', $this->respond('{"action":"adminList"}', self::ROOT)->json);
    }

    public function testUnknownActionIsRejected(): void
    {
        [$status, $body] = $this->call('{"action":"castle"}', self::ALICE);

        self::assertSame(400, $status);
        self::assertSame('Action inconnue.', $body['error']);
    }

    /** @return array{int, array<string, mixed>} */
    private function call(string $json, ?string $userId): array
    {
        $response = $this->respond($json, $userId);

        return [$response->status, json_decode($response->json, true, 512, JSON_THROW_ON_ERROR)];
    }

    private function respond(string $json, ?string $userId): JsonResponse
    {
        $this->as($userId);
        $service = $this->service();

        return (new HttpKernel(new ExceptionMapper(fn() => null)))
            ->handle('POST', $json, fn() => (new GamesController($service))->routes());
    }

    public function testGetWithTheKnownVersionAnswersUnchanged(): void
    {
        $game = $this->call('{"action":"create","mode":"local"}', self::ALICE)[1]['game'];
        $get = static fn(string $known): string => json_encode(['action' => 'get', 'id' => $game['id'], 'known' => $known], JSON_THROW_ON_ERROR);

        self::assertSame('{"ok":true,"unchanged":true}', $this->respond($get($game['version']), self::ALICE)->json);

        [$status, $body] = $this->call($get('autre'), self::ALICE);
        self::assertSame(200, $status);
        self::assertSame($game['id'], $body['game']['id']);
    }

    public function testGetKnownMustBeAString(): void
    {
        $id = $this->call('{"action":"create","mode":"local"}', self::ALICE)[1]['game']['id'];

        [$status, $body] = $this->call('{"action":"get","id":"' . $id . '","known":12}', self::ALICE);

        self::assertSame(422, $status);
        self::assertSame('Paramètre « known » manquant ou invalide.', $body['error']);
    }

    public function testHistoryAnswersWithGamesAndMore(): void
    {
        [$status, $body] = $this->call('{"action":"history"}', self::ALICE);

        self::assertSame(200, $status);
        self::assertSame(['ok' => true, 'games' => [], 'more' => false], $body);
        self::assertSame(422, $this->call('{"action":"history","offset":"20"}', self::ALICE)[0]);
        self::assertSame(403, $this->call('{"action":"history"}', null)[0]);
    }

    public function testReplayAnswersWithTheFrames(): void
    {
        $id = $this->call('{"action":"create","mode":"local"}', self::ALICE)[1]['game']['id'];
        $this->call('{"action":"move","id":"' . $id . '","uci":"e2e4","ply":0}', self::ALICE);

        [$status, $body] = $this->call('{"action":"replay","id":"' . $id . '"}', self::ALICE);

        self::assertSame(200, $status);
        self::assertSame(['ok', 'replay'], array_keys($body));
        self::assertSame($id, $body['replay']['id']);
        self::assertCount(2, $body['replay']['frames']);
        self::assertSame(404, $this->call('{"action":"replay","id":"' . $id . '"}', self::BOB)[0]);
    }
}
