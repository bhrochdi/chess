<?php

declare(strict_types=1);

namespace LeCercle\Tests\Unit\Game;

use LeCercle\Game\GameService;
use LeCercle\Shared\ConflictException;
use LeCercle\Shared\ForbiddenException;
use LeCercle\Shared\NotFoundException;
use LeCercle\Shared\ValidationException;
use LeCercle\Tests\Support\TrackingTransactionRunner;
use LeCercle\Tests\Support\TransactionAwareAdminJournal;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class GameAdminTest extends TestCase
{
    use GameServiceFixture;

    protected function setUp(): void
    {
        $this->setUpFixture();
    }

    /** @return iterable<string, array{\Closure(GameService, string): mixed}> */
    public static function adminActions(): iterable
    {
        yield 'adminList' => [static fn(GameService $s, string $id): mixed => $s->adminList()];
        yield 'adminClose' => [static fn(GameService $s, string $id): mixed => $s->adminClose($id, null)];
        yield 'adminDelete' => [static function (GameService $s, string $id): mixed {
            $s->adminDelete($id);

            return null;
        }];
        yield 'purge' => [static fn(GameService $s, string $id): mixed => $s->purgeFinished()];
    }

    /** @param \Closure(GameService, string): mixed $action */
    #[DataProvider('adminActions')]
    public function testNonAdminIsRefusedEveryAdminAction(\Closure $action): void
    {
        $service = $this->service();
        $id = $this->onlineGame($service);
        $this->as(self::ALICE);

        try {
            $action($service, $id);
            self::fail('Une action d’administration doit être refusée.');
        } catch (ForbiddenException $e) {
            self::assertSame('Action réservée aux administrateurs.', $e->getMessage());
        }
        self::assertSame([], $this->journal->entries);
        self::assertSame('playing', $service->get($id)['status']);
    }

    public function testAdminCloseDefaultsToADraw(): void
    {
        $service = $this->service();
        $id = $this->onlineGame($service);
        $this->as(self::ROOT);

        $view = $service->adminClose($id, null);

        self::assertSame(['finished', '1/2-1/2', 'admin'], [$view['status'], $view['result'], $view['reason']]);
        self::assertSame([['actor' => self::ROOT, 'action' => 'Clôture administrative de la partie ' . $id]], $this->journal->entries);
    }

    public function testAdminCloseWithAChosenResult(): void
    {
        $service = $this->service();
        $id = $this->onlineGame($service);
        $this->as(self::ROOT);

        self::assertSame('1-0', $service->adminClose($id, '1-0')['result']);
    }

    public function testAdminCloseWithAnUnknownResultIsInvalid(): void
    {
        $service = $this->service();
        $id = $this->onlineGame($service);
        $this->as(self::ROOT);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Résultat inconnu.');
        $service->adminClose($id, 'x');
    }

    public function testAdminCloseOfAFinishedGameIsAConflict(): void
    {
        $service = $this->service();
        $id = $this->onlineGame($service);
        $this->as(self::ROOT);
        $service->adminClose($id, null);

        $this->expectException(ConflictException::class);
        $this->expectExceptionMessage('La partie est déjà terminée.');
        $service->adminClose($id, null);
    }

    public function testAdminDeleteRemovesTheGame(): void
    {
        $service = $this->service();
        $id = $this->onlineGame($service);
        $this->as(self::ROOT);

        $service->adminDelete($id);

        self::assertSame([['actor' => self::ROOT, 'action' => 'Suppression de la partie ' . $id]], $this->journal->entries);
        $this->expectException(NotFoundException::class);
        $service->get($id);
    }

    public function testPurgeDeletesOnlyFinishedGames(): void
    {
        $service = $this->service();
        $first = $this->onlineGame($service);
        $second = $this->onlineGame($service);
        $playing = $this->onlineGame($service);
        $this->as(self::ROOT);
        $service->adminClose($first, null);
        $service->adminClose($second, '0-1');
        $this->journal->entries = [];

        self::assertSame(2, $service->purgeFinished());

        self::assertSame([$playing], array_column($service->adminList(), 'id'));
        self::assertSame([['actor' => self::ROOT, 'action' => 'Purge de 2 partie(s) terminée(s)']], $this->journal->entries);
    }

    public function testAdminListShowsLocalAndComputerGamesToo(): void
    {
        $service = $this->service();
        $this->as(self::ALICE);
        $local = $service->create('local', null, null, null)['id'];
        $computer = $service->create('ai', 'ai-2', null, null)['id'];
        $this->as(self::ROOT);

        $ids = array_column($service->adminList(), 'id');

        self::assertContains($local, $ids);
        self::assertContains($computer, $ids);
    }

    public function testJournalIsWrittenInsideTheTransaction(): void
    {
        $runner = new TrackingTransactionRunner();
        $journal = new TransactionAwareAdminJournal($runner);
        $service = $this->service(transactions: $runner, journal: $journal);
        $first = $this->onlineGame($service);
        $second = $this->onlineGame($service);
        $this->as(self::ROOT);

        $service->adminClose($first, null);
        $service->adminDelete($second);
        $service->purgeFinished();

        self::assertNotSame([], $journal->entries);
        self::assertSame([true], array_values(array_unique(array_column($journal->entries, 'inside'))));
    }
}
