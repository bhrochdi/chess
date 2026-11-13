<?php

declare(strict_types=1);

namespace LeCercle\Tests\Unit\Infra\Http\Api;

use LeCercle\Admin\AdminService;
use LeCercle\Admin\DashboardCounts;
use LeCercle\Admin\JournalReader;
use LeCercle\Infra\Http\Api\AdminController;
use LeCercle\Infra\Http\ExceptionMapper;
use LeCercle\Infra\Http\HttpKernel;
use LeCercle\Infra\Http\JsonResponse;
use LeCercle\Shared\Access;
use LeCercle\Tests\Support\FakeSession;
use LeCercle\Tests\Support\FixedClock;
use LeCercle\Tests\Support\InMemoryMembers;
use LeCercle\Tests\Support\MemberFactory;
use PHPUnit\Framework\TestCase;

final class AdminControllerTest extends TestCase
{
    public function testAnonymousNeedsALogin(): void
    {
        self::assertSame('{"ok":false,"error":"Connexion requise."}', $this->respond('{"action":"journal"}', null)->json);
    }

    public function testEmptyJournalEncodesAsAList(): void
    {
        self::assertSame('{"ok":true,"entries":[]}', $this->respond('{"action":"journal"}', 'u000000000009')->json);
    }

    public function testDashboardAnswersTheCounts(): void
    {
        self::assertSame(
            '{"ok":true,"counts":{"accounts":1,"online":0,"open":0,"playing":0,"finished":0,"roomMessages":0}}',
            $this->respond('{"action":"dashboard"}', 'u000000000009')->json,
        );
    }

    private function respond(string $json, ?string $userId): JsonResponse
    {
        $members = new InMemoryMembers(MemberFactory::admin('u000000000009', 'root'));
        $service = new AdminService(
            new Access(new FakeSession($userId), $members),
            $members,
            new FixedClock(new \DateTimeImmutable('2026-11-12 10:00:00.000', new \DateTimeZone('UTC'))),
            new class implements JournalReader {
                public function latest(int $limit): array
                {
                    return [];
                }
            },
            new class implements DashboardCounts {
                public function counts(\DateTimeImmutable $onlineSince): array
                {
                    return ['accounts' => 1, 'online' => 0, 'open' => 0, 'playing' => 0, 'finished' => 0, 'roomMessages' => 0];
                }
            },
        );

        return (new HttpKernel(new ExceptionMapper(fn() => null)))
            ->handle('POST', $json, fn() => (new AdminController($service))->routes());
    }
}
