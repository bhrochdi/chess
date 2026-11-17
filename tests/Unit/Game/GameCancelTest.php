<?php

declare(strict_types=1);

namespace LeCercle\Tests\Unit\Game;

use LeCercle\Game\GameService;
use LeCercle\Shared\ConflictException;
use LeCercle\Shared\ForbiddenException;
use LeCercle\Shared\NotFoundException;
use PHPUnit\Framework\TestCase;

/** Annulation d'un défi en ligne que personne n'a rejoint. */
final class GameCancelTest extends TestCase
{
    use GameServiceFixture;

    private GameService $service;

    protected function setUp(): void
    {
        $this->setUpFixture();
        $this->service = $this->service();
    }

    private function challenge(): string
    {
        $this->as(self::ALICE);

        return $this->service->create('online', null, ['base' => 300, 'inc' => 0], 'w')['id'];
    }

    /**
     * @param class-string<\Throwable> $class
     * @param \Closure(): mixed        $call
     */
    private function assertRefused(string $class, string $message, \Closure $call): void
    {
        try {
            $call();
            self::fail('L’annulation devait être refusée.');
        } catch (\Throwable $e) {
            self::assertInstanceOf($class, $e);
            self::assertSame($message, $e->getMessage());
        }
    }

    public function testTheAuthorCancelsAnOpenChallenge(): void
    {
        $id = $this->challenge();

        $this->service->cancel($id);

        self::assertSame([], $this->service->lobby());
        self::assertSame([], $this->journal->entries, 'pas une action d’administration');
        $this->expectException(NotFoundException::class);
        $this->service->get($id);
    }

    public function testCancellingLocksTheGame(): void
    {
        $id = $this->challenge();
        $this->games->lockedIds = [];

        $this->service->cancel($id);

        self::assertSame([$id], $this->games->lockedIds);
    }

    public function testAnotherPlayerCannotCancelIt(): void
    {
        $id = $this->challenge();
        $this->as(self::BOB);

        $this->assertRefused(ForbiddenException::class, 'Seul l’auteur du défi peut l’annuler.', fn() => $this->service->cancel($id));

        self::assertSame([$id], array_column($this->service->lobby(), 'id'));
    }

    public function testAJoinedChallengeCanNoLongerBeCancelled(): void
    {
        $id = $this->onlineGame($this->service);

        $this->assertRefused(
            ConflictException::class,
            'Seul un défi qui attend encore un adversaire peut être annulé.',
            fn() => $this->service->cancel($id),
        );

        self::assertSame('playing', $this->service->get($id)['status']);
    }

    public function testAFinishedChallengeCannotBeCancelled(): void
    {
        $id = $this->challenge();
        $this->as(self::ROOT);
        $this->service->adminClose($id, null);
        $this->as(self::ALICE);

        $this->assertRefused(
            ConflictException::class,
            'Seul un défi qui attend encore un adversaire peut être annulé.',
            fn() => $this->service->cancel($id),
        );
    }

    public function testALocalGameIsNotAChallenge(): void
    {
        $this->as(self::ALICE);
        $id = $this->service->create('local', null, null, null)['id'];

        $this->assertRefused(
            ConflictException::class,
            'Seul un défi qui attend encore un adversaire peut être annulé.',
            fn() => $this->service->cancel($id),
        );
    }

    public function testCancellingNeedsAnAccountAndAnExistingGame(): void
    {
        $id = $this->challenge();
        $this->as(null);
        $this->assertRefused(ForbiddenException::class, 'Connexion requise.', fn() => $this->service->cancel($id));

        $this->as(self::ALICE);
        $this->assertRefused(NotFoundException::class, 'Partie introuvable.', fn() => $this->service->cancel('g000000000999'));
    }

    public function testASuspendedAuthorCannotCancel(): void
    {
        $this->as(self::SUSPENDED);

        $this->assertRefused(
            ForbiddenException::class,
            'Compte suspendu par l’administration.',
            fn() => $this->service->cancel('g000000000001'),
        );
    }
}
