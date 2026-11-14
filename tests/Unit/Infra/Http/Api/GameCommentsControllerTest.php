<?php

declare(strict_types=1);

namespace LeCercle\Tests\Unit\Infra\Http\Api;

use LeCercle\Chat\GameCommentService;
use LeCercle\Chat\MessageViews;
use LeCercle\Infra\Http\Api\GameCommentsController;
use LeCercle\Infra\Http\ExceptionMapper;
use LeCercle\Infra\Http\HttpKernel;
use LeCercle\Infra\Http\JsonResponse;
use LeCercle\Shared\Access;
use LeCercle\Shared\Millis;
use LeCercle\Tests\Support\FakeSession;
use LeCercle\Tests\Support\FixedClock;
use LeCercle\Tests\Support\InMemoryGameCommentRepository;
use LeCercle\Tests\Support\InMemoryMembers;
use LeCercle\Tests\Support\KnownGames;
use LeCercle\Tests\Support\MemberFactory;
use PHPUnit\Framework\TestCase;

final class GameCommentsControllerTest extends TestCase
{
    private const ALICE = 'u000000000001';
    private const GAME = 'g0123456789ab';

    private InMemoryGameCommentRepository $comments;
    private InMemoryMembers $members;

    protected function setUp(): void
    {
        $this->comments = new InMemoryGameCommentRepository();
        $this->members = new InMemoryMembers(MemberFactory::user(self::ALICE, 'alice'));
    }

    public function testListOfAnUnknownGameIdIs404(): void
    {
        [$status, $body] = $this->call('{"action":"list","gameId":"x"}');

        self::assertSame(404, $status);
        self::assertSame('Partie introuvable.', $body['error']);
    }

    public function testEmptyListEncodesAsAList(): void
    {
        [$status, $body] = $this->call('{"action":"list","gameId":"' . self::GAME . '"}');

        self::assertSame(200, $status);
        self::assertSame([], $body['comments']);
        self::assertStringContainsString('"comments":[]', $this->respond('{"action":"list","gameId":"' . self::GAME . '"}', null)->json);
    }

    public function testSendNeedsALogin(): void
    {
        [$status, $body] = $this->call('{"action":"send","gameId":"' . self::GAME . '","text":"Joli"}');

        self::assertSame(403, $status);
        self::assertSame('Connexion requise.', $body['error']);
    }

    public function testSendThenList(): void
    {
        [$status, $body] = $this->call('{"action":"send","gameId":"' . self::GAME . '","text":"Joli"}', self::ALICE);
        self::assertSame(200, $status);
        self::assertSame('alice', $body['comment']['author']['pseudo']);

        [, $body] = $this->call('{"action":"list","gameId":"' . self::GAME . '"}');
        self::assertSame(['Joli'], array_column($body['comments'], 'text'));
    }

    /** @return array{int, array<string, mixed>} */
    private function call(string $json, ?string $userId = null): array
    {
        $response = $this->respond($json, $userId);

        return [$response->status, json_decode($response->json, true, 512, JSON_THROW_ON_ERROR)];
    }

    private function respond(string $json, ?string $userId): JsonResponse
    {
        $service = new GameCommentService(
            $this->comments,
            new KnownGames('g0123456789ab'),
            new Access(new FakeSession($userId), $this->members),
            new FixedClock(Millis::toDateTime(1794477600123)),
            new MessageViews($this->members),
        );

        return (new HttpKernel(new ExceptionMapper(fn() => null)))
            ->handle('POST', $json, fn() => (new GameCommentsController($service))->routes());
    }
}
