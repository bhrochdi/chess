<?php

declare(strict_types=1);

namespace LeCercle\Tests\Unit\Infra\Http\Api;

use LeCercle\Chat\DirectMessageService;
use LeCercle\Chat\MessageViews;
use LeCercle\Infra\Http\Api\DirectMessagesController;
use LeCercle\Infra\Http\ExceptionMapper;
use LeCercle\Infra\Http\HttpKernel;
use LeCercle\Infra\Http\JsonResponse;
use LeCercle\Shared\Access;
use LeCercle\Shared\Millis;
use LeCercle\Tests\Support\FakeSession;
use LeCercle\Tests\Support\FixedClock;
use LeCercle\Tests\Support\InMemoryDirectMessageRepository;
use LeCercle\Tests\Support\InMemoryMembers;
use LeCercle\Tests\Support\MemberFactory;
use PHPUnit\Framework\TestCase;

final class DirectMessagesControllerTest extends TestCase
{
    private const ALICE = 'u000000000001';
    private const BOB = 'u000000000002';

    private InMemoryDirectMessageRepository $repo;
    private InMemoryMembers $members;

    protected function setUp(): void
    {
        $this->repo = new InMemoryDirectMessageRepository();
        $this->members = new InMemoryMembers(
            MemberFactory::user(self::ALICE, 'alice'),
            MemberFactory::user(self::BOB, 'bob'),
        );
    }

    public function testOpenAnswersWithThePeer(): void
    {
        [$status, $body] = $this->call('{"action":"open","pseudo":"bob"}', self::ALICE);

        self::assertSame(200, $status);
        self::assertSame(['id' => self::BOB, 'pseudo' => 'bob'], $body['peer']);
    }

    public function testOpenUnknownPlayerIs404(): void
    {
        [$status, $body] = $this->call('{"action":"open","pseudo":"nobody"}', self::ALICE);

        self::assertSame(404, $status);
        self::assertSame('Joueur introuvable.', $body['error']);
    }

    public function testThreadsNeedsALogin(): void
    {
        [$status, $body] = $this->call('{"action":"threads"}');

        self::assertSame(403, $status);
        self::assertSame('Connexion requise.', $body['error']);
    }

    public function testThreadsExposesThreadsAndUnreadTotalAtTheTopLevel(): void
    {
        $this->call('{"action":"send","peerId":"' . self::BOB . '","text":"Salut"}', self::ALICE);
        [$status, $body] = $this->call('{"action":"threads"}', self::BOB);

        self::assertSame(200, $status);
        self::assertSame(['ok', 'threads', 'unreadTotal'], array_keys($body));
        self::assertSame(1, $body['unreadTotal']);
        self::assertSame('alice', $body['threads'][0]['peer']['pseudo']);
    }

    public function testEmptyThreadsEncodeAsAList(): void
    {
        $response = $this->raw('{"action":"threads"}', self::ALICE);

        self::assertStringContainsString('"threads":[]', $response);
    }

    public function testSendThenConversation(): void
    {
        [$status, $body] = $this->call('{"action":"send","peerId":"' . self::BOB . '","text":"Salut"}', self::ALICE);
        self::assertSame(200, $status);
        self::assertSame('Salut', $body['message']['text']);

        [$status, $body] = $this->call('{"action":"conversation","peerId":"' . self::BOB . '"}', self::ALICE);
        self::assertSame(200, $status);
        self::assertSame(['Salut'], array_column($body['messages'], 'text'));
    }

    public function testConversationMarksTheThreadAsRead(): void
    {
        $this->call('{"action":"send","peerId":"' . self::ALICE . '","text":"Salut"}', self::BOB);
        [, $body] = $this->call('{"action":"threads"}', self::ALICE);
        self::assertSame(1, $body['unreadTotal']);

        $this->call('{"action":"conversation","peerId":"' . self::BOB . '"}', self::ALICE);

        [, $body] = $this->call('{"action":"threads"}', self::ALICE);
        self::assertSame(0, $body['unreadTotal']);
    }

    private function raw(string $json, ?string $userId): string
    {
        return $this->respond($json, $userId)->json;
    }

    private function respond(string $json, ?string $userId): JsonResponse
    {
        $service = new DirectMessageService(
            $this->repo,
            new Access(new FakeSession($userId), $this->members),
            $this->members,
            new FixedClock(Millis::toDateTime(1794477600123)),
            new MessageViews($this->members),
        );

        return (new HttpKernel(new ExceptionMapper(fn() => null)))
            ->handle('POST', $json, fn() => (new DirectMessagesController($service))->routes());
    }

    /** @return array{int, array<string, mixed>} */
    private function call(string $json, ?string $userId = null): array
    {
        $response = $this->respond($json, $userId);

        return [$response->status, json_decode($response->json, true, 512, JSON_THROW_ON_ERROR)];
    }
}
