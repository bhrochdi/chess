<?php

declare(strict_types=1);

namespace LeCercle\Tests\Unit\Infra\Http\Api;

use LeCercle\Chat\MessageViews;
use LeCercle\Chat\Room;
use LeCercle\Chat\RoomService;
use LeCercle\Infra\Http\Api\RoomsController;
use LeCercle\Infra\Http\ExceptionMapper;
use LeCercle\Infra\Http\HttpKernel;
use LeCercle\Infra\Http\JsonResponse;
use LeCercle\Shared\Access;
use LeCercle\Shared\Millis;
use LeCercle\Tests\Support\FakeSession;
use LeCercle\Tests\Support\FixedClock;
use LeCercle\Tests\Support\ImmediateTransactionRunner;
use LeCercle\Tests\Support\InMemoryMembers;
use LeCercle\Tests\Support\InMemoryRoomMessageRepository;
use LeCercle\Tests\Support\InMemoryRoomRepository;
use LeCercle\Tests\Support\MemberFactory;
use LeCercle\Tests\Support\RecordingAdminJournal;
use PHPUnit\Framework\TestCase;

final class RoomsControllerTest extends TestCase
{
    private const ALICE = 'u000000000001';
    private const ROOT = 'u000000000009';

    private \DateTimeImmutable $t0;
    private InMemoryRoomRepository $rooms;
    private InMemoryRoomMessageRepository $messages;
    private InMemoryMembers $members;
    private RecordingAdminJournal $journal;

    public function testListIsPublicAndExposesTheRoomShape(): void
    {
        [$status, $body] = $this->call('{"action":"list"}');

        self::assertSame(200, $status);
        self::assertTrue($body['ok']);
        self::assertSame(
            ['id', 'name', 'description', 'position', 'archived', 'isDefault', 'createdBy', 'createdAt'],
            array_keys($body['rooms'][0]),
        );
    }

    public function testListAllNeedsAnAdminAndAnswersWithRooms(): void
    {
        [$status, $body] = $this->call('{"action":"listAll"}', self::ALICE);
        self::assertSame(403, $status);
        self::assertSame('Action réservée aux administrateurs.', $body['error']);

        [$status, $body] = $this->call('{"action":"listAll"}', self::ROOT);
        self::assertSame(200, $status);
        self::assertSame('general', $body['rooms'][0]['id']);
    }

    public function testMessagesAnswersWithMessages(): void
    {
        [$status, $body] = $this->call('{"action":"messages","roomId":"general"}');

        self::assertSame(200, $status);
        self::assertSame([], $body['messages']);
        self::assertStringContainsString('"messages":[]', $this->raw('{"action":"messages","roomId":"general"}'));
    }

    public function testSendNeedsALogin(): void
    {
        [$status, $body] = $this->call('{"action":"send","roomId":"general","text":"Salut"}');

        self::assertSame(403, $status);
        self::assertSame('Connexion requise.', $body['error']);
    }

    public function testSendAsAMember(): void
    {
        [$status, $body] = $this->call('{"action":"send","roomId":"general","text":"Salut"}', self::ALICE);

        self::assertSame(200, $status);
        self::assertSame('Salut', $body['message']['text']);
    }

    public function testCreateIsRefusedToANonAdmin(): void
    {
        [$status, $body] = $this->call('{"action":"create","name":"Analyses"}', self::ALICE);

        self::assertSame(403, $status);
        self::assertSame('Action réservée aux administrateurs.', $body['error']);
    }

    public function testCreateWithoutDescriptionUsesAnEmptyOne(): void
    {
        [$status, $body] = $this->call('{"action":"create","name":"Analyses"}', self::ROOT);

        self::assertSame(200, $status);
        self::assertSame('', $body['room']['description']);
        self::assertSame('Analyses', $body['room']['name']);
    }

    public function testRenameAnswersWithTheRoom(): void
    {
        $this->call('{"action":"create","name":"Analyses"}', self::ROOT);
        [$status, $body] = $this->call('{"action":"rename","roomId":"general","name":"Accueil"}', self::ROOT);

        self::assertSame(200, $status);
        self::assertSame('Accueil', $body['room']['name']);
    }

    public function testArchiveRequiresARealBoolean(): void
    {
        [$status, $body] = $this->call('{"action":"archive","roomId":"general","archived":"yes"}', self::ROOT);

        self::assertSame(422, $status);
        self::assertSame('Paramètre « archived » manquant ou invalide.', $body['error']);
    }

    public function testMoveAnswersWithAllRooms(): void
    {
        $this->call('{"action":"create","name":"Analyses"}', self::ROOT);
        [$status, $body] = $this->call('{"action":"move","roomId":"general","direction":"down"}', self::ROOT);

        self::assertSame(200, $status);
        self::assertSame(['Analyses', 'Général'], array_column($body['rooms'], 'name'));
    }

    public function testDeleteMessageRequiresAnIntegerId(): void
    {
        [$status, $body] = $this->call('{"action":"deleteMessage","roomId":"general","messageId":"7"}', self::ROOT);

        self::assertSame(422, $status);
        self::assertSame('Paramètre « messageId » manquant ou invalide.', $body['error']);
    }

    public function testDeleteMessageRemovesOnlyThatMessageAndClearRemovesTheRest(): void
    {
        $this->call('{"action":"send","roomId":"general","text":"un"}', self::ALICE);
        $this->call('{"action":"send","roomId":"general","text":"deux"}', self::ALICE);

        [$status, $body] = $this->call('{"action":"deleteMessage","roomId":"general","messageId":1}', self::ROOT);
        self::assertSame([200, ['ok' => true]], [$status, $body]);
        [, $body] = $this->call('{"action":"messages","roomId":"general"}');
        self::assertSame(['deux'], array_column($body['messages'], 'text'));

        [$status, $body] = $this->call('{"action":"clear","roomId":"general"}', self::ROOT);
        self::assertSame([200, ['ok' => true]], [$status, $body]);
        self::assertStringContainsString('"messages":[]', $this->raw('{"action":"messages","roomId":"general"}'));
    }

    public function testArchiveWithTrueArchivesAndHidesTheRoomFromTheList(): void
    {
        $this->call('{"action":"create","name":"Analyses"}', self::ROOT);
        [, $list] = $this->call('{"action":"list"}');
        $id = $list['rooms'][1]['id'];

        [$status, $body] = $this->call('{"action":"archive","roomId":"' . $id . '","archived":true}', self::ROOT);

        self::assertSame(200, $status);
        self::assertTrue($body['room']['archived']);
        [, $list] = $this->call('{"action":"list"}');
        self::assertSame(['general'], array_column($list['rooms'], 'id'));

        [, $body] = $this->call('{"action":"archive","roomId":"' . $id . '","archived":false}', self::ROOT);
        self::assertFalse($body['room']['archived']);
    }

    public function testTheDefaultRoomCannotBeDeleted(): void
    {
        [$status, $body] = $this->call('{"action":"delete","roomId":"general"}', self::ROOT);

        self::assertSame(409, $status);
        self::assertSame('Le salon « Général » ne peut pas être supprimé.', $body['error']);
    }

    public function testDeleteRemovesAnotherRoom(): void
    {
        $this->call('{"action":"create","name":"Analyses"}', self::ROOT);
        [, $list] = $this->call('{"action":"list"}');
        $id = $list['rooms'][1]['id'];

        [$status, $body] = $this->call('{"action":"delete","roomId":"' . $id . '"}', self::ROOT);

        self::assertSame([200, ['ok' => true]], [$status, $body]);
    }

    public function testUnknownActionIs400(): void
    {
        [$status] = $this->call('{"action":"nope"}');

        self::assertSame(400, $status);
    }

    protected function setUp(): void
    {
        $this->t0 = Millis::toDateTime(1794477600123);
        $this->rooms = new InMemoryRoomRepository();
        $this->rooms->add(new Room('general', 'Général', '', 0, false, null, $this->t0));
        $this->messages = new InMemoryRoomMessageRepository();
        $this->members = new InMemoryMembers(
            MemberFactory::user(self::ALICE, 'alice'),
            MemberFactory::admin(self::ROOT, 'root'),
        );
        $this->journal = new RecordingAdminJournal();
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
        $service = new RoomService(
            $this->rooms,
            $this->messages,
            new Access(new FakeSession($userId), $this->members),
            new FixedClock($this->t0),
            new MessageViews($this->members),
            $this->journal,
            new ImmediateTransactionRunner(),
            $this->members,
        );

        return (new HttpKernel(new ExceptionMapper(fn() => null)))
            ->handle('POST', $json, fn() => (new RoomsController($service))->routes());
    }
}
