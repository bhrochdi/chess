<?php

declare(strict_types=1);

namespace LeCercle\Tests\Unit\Game;

use LeCercle\Game\GameService;
use LeCercle\Shared\TooManyRequestsException;
use LeCercle\Shared\ValidationException;
use PHPUnit\Framework\TestCase;

/** Créations de parties limitées par compte : défis en ligne d'une part, parties locales ou contre l'ordinateur de l'autre. */
final class GameCreationLimitTest extends TestCase
{
    use GameServiceFixture;

    private GameService $service;

    protected function setUp(): void
    {
        $this->setUpFixture();
        $this->service = $this->service();
        $this->as(self::ALICE);
    }

    private function challenges(int $count): void
    {
        for ($i = 0; $i < $count; ++$i) {
            $this->service->create('online', null, null, 'w');
        }
    }

    private function localGames(int $count): void
    {
        for ($i = 0; $i < $count; ++$i) {
            $this->service->create('local', null, null, null);
        }
    }

    /** @param \Closure(): mixed $create */
    private function assertRefused(string $message, \Closure $create): void
    {
        try {
            $create();
            self::fail('La création devait être refusée.');
        } catch (TooManyRequestsException $e) {
            self::assertSame($message, $e->getMessage());
        }
    }

    public function testLimitsPerTenMinutes(): void
    {
        self::assertSame([5, 30, 600_000], [GameService::MAX_CHALLENGES, GameService::MAX_LOCAL_GAMES, GameService::CREATE_WINDOW_MS]);
    }

    public function testChallengeBeyondTheLimitIsRefusedAndNotCreated(): void
    {
        $this->challenges(GameService::MAX_CHALLENGES);

        $this->assertRefused(
            'Tu as créé trop de défis. Patiente quelques minutes.',
            fn() => $this->service->create('online', null, null, 'w'),
        );

        self::assertCount(GameService::MAX_CHALLENGES, $this->service->lobby());
    }

    public function testLocalGameBeyondTheLimitIsRefused(): void
    {
        $this->localGames(GameService::MAX_LOCAL_GAMES - 1);
        $this->service->create('ai', 'ai-2', null, null);

        $this->assertRefused(
            'Tu crées des parties trop vite. Patiente quelques minutes.',
            fn() => $this->service->create('local', null, null, null),
        );
    }

    public function testChallengesAndLocalGamesAreCountedSeparately(): void
    {
        $this->challenges(GameService::MAX_CHALLENGES);

        self::assertSame('playing', $this->service->create('local', null, null, null)['status']);
    }

    public function testEachAccountHasItsOwnLimit(): void
    {
        $this->challenges(GameService::MAX_CHALLENGES);

        $this->as(self::BOB);

        self::assertSame('open', $this->service->create('online', null, null, 'w')['status']);
    }

    public function testCreationResumesOnceTheWindowHasPassed(): void
    {
        $this->challenges(GameService::MAX_CHALLENGES);
        $this->clock->advanceMs(GameService::CREATE_WINDOW_MS);

        self::assertSame('open', $this->service->create('online', null, null, 'w')['status']);
    }

    public function testAnInvalidRequestDoesNotCount(): void
    {
        for ($i = 0; $i < GameService::MAX_CHALLENGES + 3; ++$i) {
            try {
                $this->service->create('online', null, null, 'vert');
                self::fail('Une couleur inconnue devait être refusée.');
            } catch (ValidationException) {
            }
        }

        $this->challenges(GameService::MAX_CHALLENGES);

        self::assertCount(GameService::MAX_CHALLENGES, $this->service->lobby());
    }

    public function testTournamentPairingsAreNotLimited(): void
    {
        $this->challenges(GameService::MAX_CHALLENGES);

        for ($i = 0; $i < 10; ++$i) {
            $this->service->createForPairing(self::ALICE, self::BOB, null);
        }

        self::assertCount(GameService::MAX_CHALLENGES + 10, $this->service->lobby());
    }
}
