<?php

declare(strict_types=1);

namespace LeCercle\Tests\Unit\Game;

use LeCercle\Chess\Ai\AiLevel;
use LeCercle\Game\GameService;
use LeCercle\Game\GameStatus;
use LeCercle\Shared\ConflictException;
use LeCercle\Shared\Millis;
use LeCercle\Shared\ValidationException;
use LeCercle\Tests\Support\CountingMembers;
use LeCercle\Tests\Support\FixedClock;
use LeCercle\Tests\Support\ScriptedComputerPlayers;
use LeCercle\Tests\Unit\Chess\Ai\FixedRandom;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class GameServiceTest extends TestCase
{
    use GameServiceFixture;

    protected function setUp(): void
    {
        $this->setUpFixture();
    }

    public function testCreateLocalGame(): void
    {
        $this->as(self::ALICE);

        $view = $this->service()->create('local', null, null, null);

        self::assertSame('g000000000001', $view['id']);
        self::assertSame('local', $view['mode']);
        self::assertSame('playing', $view['status']);
        self::assertSame('both', $view['yourColor']);
        self::assertSame(GameStatus::Playing, $this->games->get('g000000000001')->status());
    }

    public function testCreateComputerGameAndTheComputerReplies(): void
    {
        $computer = new ScriptedComputerPlayers($this->rules, ['e7e5']);
        $service = $this->service($computer);
        $this->as(self::ALICE);
        $id = $service->create('ai', 'ai-2', null, null)['id'];

        $view = $service->move($id, 'e2e4', 0);

        self::assertSame(['e4', 'e5'], $view['moves']);
        self::assertSame('w', $view['turn']);
        self::assertSame([AiLevel::Beginner], $computer->levelsAsked);
        self::assertSame(2, $this->games->get($id)->plyCount(), 'la réponse de l’ordinateur est enregistrée');
    }

    public function testTheComputerDoesNotPlayInALocalGame(): void
    {
        $computer = new ScriptedComputerPlayers($this->rules, ['e7e5']);
        $service = $this->service($computer);
        $this->as(self::ALICE);
        $id = $service->create('local', null, null, null)['id'];

        $view = $service->move($id, 'e2e4', 0);

        self::assertSame(['e4'], $view['moves']);
        self::assertSame([], $computer->levelsAsked);
    }

    public function testComputerThinkingTimeIsChargedToItsClock(): void
    {
        $computer = new ScriptedComputerPlayers($this->rules, ['e7e5'], fn() => $this->clock->advanceMs(2000));
        $service = $this->service($computer);
        $this->as(self::ALICE);
        $id = $service->create('ai', 'ai-3', ['base' => 300, 'inc' => 0], null)['id'];

        $clock = $service->move($id, 'e2e4', 0)['clock'];

        self::assertSame(298000, $clock['blackMs']);
        self::assertSame(300000, $clock['whiteMs'], 'premier coup : les blancs ne sont pas décomptés');
        self::assertSame('w', $clock['running']);
    }

    public function testTheResponseIsReadAtTheInstantAfterTheComputerReply(): void
    {
        $computer = new ScriptedComputerPlayers($this->rules, ['e7e5'], fn() => $this->clock->advanceMs(2000));
        $service = $this->service($computer);
        $this->as(self::ALICE);
        $id = $service->create('ai', 'ai-3', ['base' => 300, 'inc' => 0], null)['id'];

        $view = $service->move($id, 'e2e4', 0);

        self::assertSame(Millis::of($this->t0) + 2000, $view['clock']['serverNow']);
        self::assertSame(Millis::of($this->t0) + 2000, $view['updatedAt']);
    }

    public function testCreateOnlineWithRandomColour(): void
    {
        $this->as(self::ALICE);

        $black = $this->service(random: new FixedRandom(0.7))->create('online', null, null, 'random');
        $white = $this->service(random: new FixedRandom(0.2))->create('online', null, null, null);

        self::assertSame('open', $black['status']);
        self::assertSame('b', $black['yourColor']);
        self::assertNull($black['white']);
        self::assertSame('w', $white['yourColor']);
        self::assertSame('b', $this->service(random: new FixedRandom(0.5))->create('online', null, null, 'random')['yourColor'], 'tirage 0,5 : noirs');
    }

    public function testCreateOnlineWithAChosenColour(): void
    {
        $this->as(self::ALICE);
        $service = $this->service(random: new FixedRandom(0.0));

        self::assertSame('b', $service->create('online', null, null, 'b')['yourColor']);
        self::assertSame('w', $service->create('online', null, null, 'w')['yourColor']);
    }

    public function testLevelAndColourAreIgnoredOutsideTheirModes(): void
    {
        $this->as(self::ALICE);

        $view = $this->service()->create('local', 'ai-9', null, 'x');

        self::assertSame('local', $view['mode']);
        self::assertNull($view['level']);
    }

    public function testCreateComputerGameKeepsItsLevel(): void
    {
        $this->as(self::ALICE);

        self::assertSame('ai-4', $this->service()->create('ai', 'ai-4', null, null)['level']);
    }

    /** @param array<mixed>|null $tc */
    #[DataProvider('invalidCreations')]
    public function testCreateValidation(string $mode, ?string $level, ?array $tc, ?string $color, string $message): void
    {
        $this->as(self::ALICE);

        try {
            $this->service()->create($mode, $level, $tc, $color);
            self::fail('ValidationException attendue');
        } catch (ValidationException $e) {
            self::assertSame($message, $e->getMessage());
        }
        self::assertSame([], $this->games->unfinishedOf(self::ALICE, 100), 'aucune partie créée');
    }

    /** @return iterable<string, array{string, ?string, ?array<mixed>, ?string, string}> */
    public static function invalidCreations(): iterable
    {
        yield 'mode inconnu' => ['chess', null, null, null, 'Mode de partie inconnu.'];
        yield 'niveau inconnu' => ['ai', 'ai-9', null, null, 'Niveau inconnu.'];
        yield 'niveau absent' => ['ai', null, null, null, 'Niveau inconnu.'];
        yield 'couleur inconnue' => ['online', null, null, 'x', 'Couleur inconnue.'];
        yield 'cadence invalide' => ['local', null, ['base' => 0, 'inc' => 0], null, 'Cadence invalide.'];
    }

    public function testJoinAndPlayOnline(): void
    {
        $service = $this->service();
        $this->as(self::ALICE);
        $id = $service->create('online', null, ['base' => 300, 'inc' => 0], 'w')['id'];
        $this->as(self::BOB);

        $joined = $service->join($id);
        self::assertSame('playing', $joined['status']);
        self::assertSame('b', $joined['yourColor']);
        self::assertSame('w', $joined['clock']['running']);

        $this->as(self::ALICE);
        $service->move($id, 'e2e4', 0);
        $this->as(self::BOB);
        $view = $service->move($id, 'e7e5', 1);

        self::assertSame(['e4', 'e5'], $view['moves']);
    }

    public function testJoiningAComputerOrLocalGameOfOneselfIsConflict(): void
    {
        $service = $this->service();
        $this->as(self::ALICE);
        $id = $service->create('local', null, null, null)['id'];

        $this->expectException(ConflictException::class);
        $this->expectExceptionMessage('Cette partie a déjà commencé.');
        $service->join($id);
    }

    public function testStalePlyIsConflict(): void
    {
        $service = $this->service();
        $this->as(self::ALICE);
        $id = $service->create('local', null, null, null)['id'];
        $service->move($id, 'e2e4', 0);

        try {
            $service->move($id, 'd2d4', 0);
            self::fail('ConflictException attendue');
        } catch (ConflictException $e) {
            self::assertSame('La partie a avancé : recharge-la.', $e->getMessage());
        }
        self::assertSame(1, $this->games->get($id)->plyCount());
    }

    public function testGetRecordsAFallenFlag(): void
    {
        $service = $this->service();
        $id = $this->onlineGame($service, ['base' => 60, 'inc' => 0]);
        $this->clock->advanceMs(61000);
        $this->as(null);

        $view = $service->get($id);

        self::assertSame('finished', $view['status']);
        self::assertSame('temps', $view['reason']);
        self::assertSame('0-1', $view['result']);
        self::assertSame(GameStatus::Finished, $this->games->get($id)->status(), 'la chute est enregistrée');
    }

    public function testGetLeavesARunningGameAlone(): void
    {
        $service = $this->service();
        $id = $this->onlineGame($service, ['base' => 60, 'inc' => 0]);
        $this->clock->advanceMs(59000);

        $view = $service->get($id);

        self::assertSame('playing', $view['status']);
        self::assertSame(1000, $view['clock']['whiteMs']);
    }

    public function testLateMoveAfterFlagFallIsNotPlayed(): void
    {
        $service = $this->service();
        $id = $this->onlineGame($service, ['base' => 60, 'inc' => 0]);
        $this->clock->advanceMs(61000);

        $view = $service->move($id, 'e2e4', 0);

        self::assertSame('finished', $view['status']);
        self::assertSame('temps', $view['reason']);
        self::assertSame([], $view['moves']);
        self::assertSame(GameStatus::Finished, $this->games->get($id)->status());
    }

    public function testResignAfterFlagFallRecordsTheFlag(): void
    {
        $service = $this->service();
        $id = $this->onlineGame($service, ['base' => 60, 'inc' => 0]);
        $this->clock->advanceMs(61000);
        $this->as(self::BOB);

        $view = $service->resign($id);

        self::assertSame('temps', $view['reason']);
        self::assertSame('0-1', $view['result']);
    }

    public function testWhiteResigns(): void
    {
        $service = $this->service();
        $id = $this->onlineGame($service);

        $view = $service->resign($id);

        self::assertSame('0-1', $view['result']);
        self::assertSame('abandon', $view['reason']);
        self::assertSame(GameStatus::Finished, $this->games->get($id)->status());
    }

    public function testOfferDraw(): void
    {
        $service = $this->service();
        $id = $this->onlineGame($service);

        $view = $service->offerDraw($id);

        self::assertSame('w', $view['drawOffer']);
        self::assertSame('w', $this->games->get($id)->drawOffer()?->value);
    }

    public function testAcceptDraw(): void
    {
        $service = $this->service();
        $id = $this->onlineGame($service);
        $service->offerDraw($id);
        $this->as(self::BOB);

        $view = $service->acceptDraw($id);

        self::assertSame('1/2-1/2', $view['result']);
        self::assertSame('accord', $view['reason']);
        self::assertSame(GameStatus::Finished, $this->games->get($id)->status());
    }

    public function testUndoInALocalGame(): void
    {
        $service = $this->service();
        $this->as(self::ALICE);
        $id = $service->create('local', null, null, null)['id'];
        $service->move($id, 'e2e4', 0);
        $service->move($id, 'e7e5', 1);

        $view = $service->undo($id);

        self::assertCount(1, $view['moves']);
        self::assertSame(1, $this->games->get($id)->plyCount());
    }

    public function testLobbyAndMine(): void
    {
        $service = $this->service();
        $this->as(self::ALICE);
        $first = $service->create('online', null, null, 'w')['id'];
        $second = $service->create('online', null, null, 'w')['id'];
        $local = $service->create('local', null, null, null)['id'];
        $this->as(self::BOB);
        $service->create('local', null, null, null);

        $this->as(null);
        self::assertSame([$first, $second], array_column($service->lobby(), 'id'));
        $this->as(self::ALICE);
        self::assertSame([$first, $second, $local], array_column($service->mine(), 'id'));
    }

    public function testLobbyKeepsFinishedGamesForTwoHours(): void
    {
        $service = $this->service();
        $id = $this->onlineGame($service);
        $service->resign($id);

        $this->clock->advanceMs(2 * 3600 * 1000);
        self::assertSame([$id], array_column($service->lobby(), 'id'));
        $this->clock->advanceMs(1);
        self::assertSame([], $service->lobby());
    }

    public function testListsAreLimited(): void
    {
        $service = $this->service();
        $this->as(self::ALICE);
        for ($i = 0; $i < GameService::LOBBY_LIMIT + 1; ++$i) {
            $service->create('online', null, null, 'w');
        }

        self::assertCount(30, $service->lobby());
        self::assertCount(10, $service->mine());
    }

    public function testEachResponseResolvesMembersOnce(): void
    {
        $members = new CountingMembers($this->members);
        $service = $this->service(members: $members);
        $id = $this->onlineGame($service);
        $service->create('online', null, null, 'b');

        $members->findManyCalls = 0;
        $service->lobby();
        self::assertSame(1, $members->findManyCalls, 'lobby');

        $members->findManyCalls = 0;
        $service->mine();
        self::assertSame(1, $members->findManyCalls, 'mine');

        $members->findManyCalls = 0;
        $service->move($id, 'e2e4', 0);
        self::assertSame(1, $members->findManyCalls, 'move');

        $members->findManyCalls = 0;
        $service->get($id);
        self::assertSame(1, $members->findManyCalls, 'get');

        $members->findManyCalls = 0;
        $service->pgn($id);
        self::assertSame(1, $members->findManyCalls, 'pgn');
    }

    public function testPgn(): void
    {
        $service = $this->service();
        $id = $this->onlineGame($service);
        $service->move($id, 'e2e4', 0);
        $this->as(self::BOB);
        $service->move($id, 'e7e5', 1);
        $service->resign($id);

        $export = $service->pgn($id);

        self::assertSame('le-cercle-g000000000001.pgn', $export['filename']);
        self::assertStringStartsWith("[Event \"Partie en ligne — Le Cercle\"]\n", $export['pgn']);
        self::assertStringContainsString("[White \"alice\"]\n[Black \"bob\"]\n[Result \"1-0\"]\n", $export['pgn']);
        self::assertStringEndsWith("\n1. e4 e5 1-0\n", $export['pgn']);
    }

    public function testPgnOfALocalGameInProgress(): void
    {
        $service = $this->service();
        $this->as(self::ALICE);
        $id = $service->create('local', null, null, null)['id'];
        $service->move($id, 'e2e4', 0);

        $pgn = $service->pgn($id)['pgn'];

        self::assertStringStartsWith("[Event \"Partie locale\"]\n", $pgn);
        self::assertStringContainsString("[White \"Joueur Blancs\"]\n[Black \"Joueur Noirs\"]\n[Result \"*\"]\n", $pgn);
        self::assertStringEndsWith("\n1. e4 *\n", $pgn);
    }

    public function testPgnDateIsTheCreationDayInUtc(): void
    {
        $this->clock = new FixedClock(new \DateTimeImmutable('2026-11-13 00:30:00', new \DateTimeZone('+01:00')));
        $service = $this->service();
        $this->as(self::ALICE);
        $id = $service->create('local', null, null, null)['id'];
        $this->clock->advanceMs(3 * 24 * 3600 * 1000);
        $service->move($id, 'e2e4', 0);

        self::assertStringContainsString("[Date \"2026.11.12\"]\n", $service->pgn($id)['pgn']);
    }
}
