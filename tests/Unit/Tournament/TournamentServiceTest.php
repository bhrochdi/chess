<?php

declare(strict_types=1);

namespace LeCercle\Tests\Unit\Tournament;

use LeCercle\Shared\Access;
use LeCercle\Shared\ConflictException;
use LeCercle\Shared\ForbiddenException;
use LeCercle\Shared\NotFoundException;
use LeCercle\Shared\ValidationException;
use LeCercle\Tests\Unit\Tournament\TournamentServiceFixture as F;
use PHPUnit\Framework\TestCase;

final class TournamentServiceTest extends TestCase
{
    private F $f;

    protected function setUp(): void
    {
        $this->f = new F();
    }

    public function testListAndGetAreAnonymous(): void
    {
        $id = $this->f->tournament('Open');
        $this->f->tournament('Blitz');

        $list = $this->f->as(null)->list();

        self::assertCount(2, $list);
        foreach ($list as $summary) {
            self::assertSame(['id', 'name', 'format', 'tc', 'status', 'playerCount', 'startedAt'], array_keys($summary));
        }
        $tournament = $this->f->service->get($id);
        self::assertSame('Open', $tournament['name']);
        self::assertFalse($tournament['registered']);
        self::assertNull($tournament['myPairing']);
    }

    public function testRegisterAndUnregister(): void
    {
        $id = $this->f->tournament();

        $t = $this->f->as(F::ALICE)->register($id);
        self::assertSame([['id' => F::ALICE, 'pseudo' => 'alice']], $t['players']);
        self::assertTrue($t['registered']);

        $t = $this->f->as(F::ALICE)->register($id);
        self::assertCount(1, $t['players'], 'une seconde inscription ne change rien');

        $t = $this->f->as(F::ALICE)->unregister($id);
        self::assertSame([], $t['players']);
        self::assertFalse($t['registered']);
    }

    public function testRegisterRefusesSuspendedAndAnonymous(): void
    {
        $id = $this->f->tournament();

        $this->assertForbidden(Access::SUSPENDED, fn() => $this->f->as(F::EVE)->register($id));
        $this->assertForbidden(Access::LOGIN_REQUIRED, fn() => $this->f->as(null)->register($id));
        $this->assertForbidden(Access::LOGIN_REQUIRED, fn() => $this->f->as(null)->unregister($id));
        self::assertSame([], $this->f->service->get($id)['players']);
    }

    public function testGuestsCannotRegisterNorUnregister(): void
    {
        $id = $this->f->tournament();
        $message = 'Un compte invité ne peut pas s\'inscrire à un tournoi.';

        $this->assertForbidden($message, fn() => $this->f->as(F::GUEST)->register($id));
        $this->assertForbidden($message, fn() => $this->f->as(F::GUEST)->unregister($id));
        self::assertSame([], $this->f->service->get($id)['players']);
    }

    public function testUnknownTournament(): void
    {
        $this->expectException(NotFoundException::class);
        $this->expectExceptionMessage('Tournoi introuvable.');

        $this->f->as(null)->get('t000000000000');
    }

    public function testRegistrationClosedIsAConflict(): void
    {
        $id = $this->f->tournament();
        $this->f->as(F::ROOT)->setRegistration($id, false);

        $this->expectException(ConflictException::class);
        $this->expectExceptionMessage('Les inscriptions sont fermées.');

        $this->f->as(F::ALICE)->register($id);
    }

    public function testCreateReturnsAnOpenTournament(): void
    {
        $t = $this->f->as(F::ROOT)->create('  Open d\'hiver  ', ' Cadence rapide ', 'round-robin', ['base' => 600, 'inc' => 5], 20);

        self::assertSame('t000000000001', $t['id']);
        self::assertSame('Open d\'hiver', $t['name']);
        self::assertSame('Cadence rapide', $t['description']);
        self::assertSame('inscriptions', $t['status']);
        self::assertTrue($t['registrationOpen']);
        self::assertSame(['base' => 600, 'inc' => 5], $t['tc']);
        self::assertNull($t['plannedRounds'], 'le nombre de rondes ne sert qu\'au suisse');
        self::assertSame(['id' => F::ROOT, 'pseudo' => 'root'], $t['createdBy']);
        self::assertSame(1794477600000, $t['createdAt']);
    }

    public function testSwissRoundsDefaultToFive(): void
    {
        self::assertSame(5, $this->f->as(F::ROOT)->create('Suisse', '', 'suisse', null, null)['plannedRounds']);
        self::assertSame(7, $this->f->as(F::ROOT)->create('Suisse 7', '', 'suisse', null, 7)['plannedRounds']);
    }

    public function testStartPairsTheFirstRound(): void
    {
        $id = $this->f->tournament(players: [F::ALICE, F::BOB]);
        $this->f->clock->advanceMs(60_000);
        $t = $this->f->as(F::ROOT)->start($id);

        self::assertSame('en-cours', $t['status']);
        self::assertSame(1, $t['currentRound']);
        self::assertSame(1794477660000, $t['startedAt']);
        self::assertSame([
            'board' => 1,
            'white' => ['id' => F::ALICE, 'pseudo' => 'alice'],
            'black' => ['id' => F::BOB, 'pseudo' => 'bob'],
            'result' => null,
            'gameId' => 'g000000000001',
        ], $t['rounds'][0]['pairings'][0]);
    }

    public function testOnlyOneRunningTournament(): void
    {
        $first = $this->f->tournament('Open', players: [F::ALICE, F::BOB]);
        $second = $this->f->tournament('Blitz', players: [F::CAROL, F::DAVE]);
        $this->f->as(F::ROOT)->start($first);

        try {
            $this->f->as(F::ROOT)->start($second);
            self::fail('un second tournoi a été lancé');
        } catch (ConflictException $e) {
            self::assertSame('Un autre tournoi est déjà en cours (un seul à la fois).', $e->getMessage());
        }
        self::assertSame('inscriptions', $this->f->service->get($second)['status']);

        $this->f->as(F::ROOT)->close($first);
        self::assertSame('en-cours', $this->f->as(F::ROOT)->start($second)['status'], 'possible une fois le premier clos');
    }

    public function testStartingTheRunningTournamentAgainSaysItStarted(): void
    {
        $id = $this->f->started();

        $this->expectException(ConflictException::class);
        $this->expectExceptionMessage('Le tournoi a déjà commencé.');

        $this->f->as(F::ROOT)->start($id);
    }

    public function testCorrectingAResultUpdatesStandings(): void
    {
        $id = $this->f->started(players: [F::ALICE, F::BOB]);

        $t = $this->f->as(F::ROOT)->setResult($id, 1, 1, '1-0');
        self::assertSame(['id' => F::ALICE, 'pseudo' => 'alice'], $t['standings'][0]['player']);
        self::assertSame(1.0, $t['standings'][0]['points']);
        self::assertSame('1-0', $t['rounds'][0]['pairings'][0]['result']);

        $t = $this->f->as(F::ROOT)->setResult($id, 1, 1, '0-1');
        self::assertSame(['id' => F::BOB, 'pseudo' => 'bob'], $t['standings'][0]['player']);
        self::assertSame(1.0, $t['standings'][0]['points']);
        self::assertSame(0.0, $t['standings'][1]['points']);

        $t = $this->f->as(F::ROOT)->setResult($id, 1, 1, null);
        self::assertNull($t['rounds'][0]['pairings'][0]['result']);
        self::assertSame(0, $t['standings'][0]['played']);
    }

    public function testNonAdminSeesOnlyStartedRounds(): void
    {
        $id = $this->f->started(players: [F::ALICE, F::BOB, F::CAROL, F::DAVE]);

        $asAlice = $this->f->as(F::ALICE)->get($id);
        self::assertCount(1, $asAlice['rounds']);
        self::assertCount(3, $this->f->as(F::ROOT)->get($id)['rounds']);
        self::assertCount(1, $this->f->as(null)->get($id)['rounds']);

        $board = null;
        foreach ($asAlice['rounds'][0]['pairings'] as $p) {
            if ($p['white']['id'] === F::ALICE || $p['black']['id'] === F::ALICE) {
                $board = $p['board'];
            }
        }
        self::assertSame(['round' => 1, 'board' => $board, 'gameId' => \sprintf('g%012d', $board)], $asAlice['myPairing']);
        self::assertTrue($asAlice['registered']);

        $this->f->as(F::ROOT)->nextRound($id, true);
        self::assertCount(2, $this->f->as(F::ALICE)->get($id)['rounds']);
        self::assertSame(2, $this->f->as(F::ALICE)->get($id)['myPairing']['round']);
    }

    public function testResultValidation(): void
    {
        $id = $this->f->started();

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Résultat inconnu.');

        $this->f->as(F::ROOT)->setResult($id, 1, 1, 'win');
    }

    public function testOnlyTheCurrentRoundCanBeCorrected(): void
    {
        $id = $this->f->started(players: [F::ALICE, F::BOB, F::CAROL, F::DAVE]);
        $this->f->as(F::ROOT)->setResult($id, 1, 1, '1-0');
        $this->f->as(F::ROOT)->nextRound($id, true);

        $this->expectException(ConflictException::class);
        $this->expectExceptionMessage('Seuls les résultats de la ronde en cours peuvent être modifiés.');

        $this->f->as(F::ROOT)->setResult($id, 1, 1, '0-1');
    }

    public function testSwissStartAndNextRoundOrderPlayersByPseudo(): void
    {
        // zoe a le plus petit identifiant mais le dernier pseudo : l'ordre initial suit les pseudos.
        $id = $this->f->started('Suisse', 'suisse', [F::ZOE, F::ALICE, F::BOB, F::CAROL], 3);

        $t = $this->f->as(F::ROOT)->get($id);
        self::assertSame([['alice', 'bob'], ['carol', 'zoe']], $this->pseudoPairs($t['rounds'][0]['pairings']));

        $this->f->as(F::ROOT)->setResult($id, 1, 1, '1/2-1/2');
        $this->f->as(F::ROOT)->setResult($id, 1, 2, '1/2-1/2');
        $t = $this->f->as(F::ROOT)->nextRound($id, false);

        self::assertSame([['alice', 'carol'], ['bob', 'zoe']], $this->pseudoPairs($t['rounds'][1]['pairings']));
    }

    public function testDeletedPlayerKeepsHisPlaceUnderAPlaceholderPseudo(): void
    {
        $id = $this->f->started(players: [F::ALICE, F::BOB]);
        $this->f->deleted[] = F::BOB;

        $t = $this->f->as(null)->get($id);

        self::assertSame(['id' => F::BOB, 'pseudo' => '(compte supprimé)'], $t['players'][1]);
    }

    public function testEachResponseLoadsMembersOnce(): void
    {
        $id = $this->f->tournament('Open', 'suisse', [F::ALICE, F::BOB, F::CAROL, F::DAVE], 3);
        $calls = [
            'get' => fn() => $this->f->as(F::ALICE)->get($id),
            'register' => fn() => $this->f->as(F::ZOE)->register($id),
            'unregister' => fn() => $this->f->as(F::ZOE)->unregister($id),
            'setRegistration' => fn() => $this->f->as(F::ROOT)->setRegistration($id, false),
            'start' => fn() => $this->f->as(F::ROOT)->start($id),
            'setResult' => fn() => $this->f->as(F::ROOT)->setResult($id, 1, 1, '1-0'),
            'setResult 2' => fn() => $this->f->as(F::ROOT)->setResult($id, 1, 2, '1-0'),
            'nextRound' => fn() => $this->f->as(F::ROOT)->nextRound($id, false),
            'close' => fn() => $this->f->as(F::ROOT)->close($id),
            'create' => fn() => $this->f->as(F::ROOT)->create('Autre', '', 'round-robin', null, null),
        ];
        foreach ($calls as $name => $call) {
            $this->f->findManyCalls = 0;
            $call();
            self::assertSame(1, $this->f->findManyCalls, $name);
        }
    }

    /**
     * @param list<array<string, mixed>> $pairings
     * @return list<list<mixed>>
     */
    private function pseudoPairs(array $pairings): array
    {
        return array_map(static fn(array $p): array => [$p['white']['pseudo'] ?? null, $p['black']['pseudo'] ?? null], $pairings);
    }

    private function assertForbidden(string $message, callable $action): void
    {
        try {
            $action();
            self::fail('action permise : ' . $message);
        } catch (ForbiddenException $e) {
            self::assertSame($message, $e->getMessage());
        }
    }
}
