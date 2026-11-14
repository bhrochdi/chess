<?php

declare(strict_types=1);

namespace LeCercle\Tests\Unit\Tournament;

use LeCercle\Shared\Access;
use LeCercle\Shared\ConflictException;
use LeCercle\Shared\ForbiddenException;
use LeCercle\Shared\NotFoundException;
use LeCercle\Shared\ValidationException;
use LeCercle\Tests\Unit\Tournament\TournamentServiceFixture as F;
use LeCercle\Tournament\TournamentService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class TournamentServiceAdminTest extends TestCase
{
    private F $f;

    protected function setUp(): void
    {
        $this->f = new F();
    }

    /** @return iterable<string, array{\Closure(TournamentService, string): mixed}> */
    public static function adminActions(): iterable
    {
        yield 'create' => [static fn(TournamentService $s, string $id): mixed => $s->create('Blitz', '', 'round-robin', null, null)];
        yield 'setRegistration' => [static fn(TournamentService $s, string $id): mixed => $s->setRegistration($id, false)];
        yield 'start' => [static fn(TournamentService $s, string $id): mixed => $s->start($id)];
        yield 'setResult' => [static fn(TournamentService $s, string $id): mixed => $s->setResult($id, 1, 1, '1-0')];
        yield 'setResult invalide' => [static fn(TournamentService $s, string $id): mixed => $s->setResult($id, 1, 1, 'win')];
        yield 'nextRound' => [static fn(TournamentService $s, string $id): mixed => $s->nextRound($id, true)];
        yield 'close' => [static fn(TournamentService $s, string $id): mixed => $s->close($id)];
        yield 'delete' => [static function (TournamentService $s, string $id): mixed {
            $s->delete($id);

            return null;
        }];
    }

    /** @param \Closure(TournamentService, string): mixed $action */
    #[DataProvider('adminActions')]
    public function testAdminActionsAreRefusedToMembers(\Closure $action): void
    {
        $id = $this->f->tournament('Open', players: [F::ALICE, F::BOB]);
        $this->f->journal->entries = [];

        try {
            $action($this->f->as(F::ALICE), $id);
            self::fail('action permise à un membre');
        } catch (ForbiddenException $e) {
            self::assertSame(Access::ADMIN_ONLY, $e->getMessage());
        }
        self::assertSame([], $this->f->journal->entries);
        self::assertSame('inscriptions', $this->f->service->get($id)['status']);
        self::assertCount(1, $this->f->service->list());
    }

    public function testJournalSentences(): void
    {
        $root = $this->f->as(F::ROOT);
        $expected = [];

        $id = (string) $root->create('Open', '', 'round-robin', null, null)['id'];
        $expected[] = 'Création du tournoi « Open »';
        $this->f->as(F::ALICE)->register($id);
        $this->f->as(F::BOB)->register($id);
        $this->f->as(F::CAROL)->register($id);
        $this->f->as(F::DAVE)->register($id);
        $this->assertJournal($expected);

        $root = $this->f->as(F::ROOT);
        $root->setRegistration($id, false);
        $expected[] = 'Tournoi « Open » : inscriptions fermées';
        $this->assertJournal($expected);
        $root->setRegistration($id, true);
        $expected[] = 'Tournoi « Open » : inscriptions ouvertes';
        $this->assertJournal($expected);

        $root->start($id);
        $expected[] = 'Lancement du tournoi « Open » (4 joueurs)';
        $this->assertJournal($expected);

        // ronde 1 du toutes rondes à 4 : alice–dave puis bob–carol
        $root->setResult($id, 1, 2, '1-0');
        $expected[] = 'Tournoi « Open » : R1 bob–carol = 1-0';
        $this->assertJournal($expected);
        $root->setResult($id, 1, 2, null);
        $expected[] = 'Tournoi « Open » : R1 bob–carol = effacé';
        $this->assertJournal($expected);
        $root->setResult($id, 1, 1, '1/2-1/2');
        $expected[] = 'Tournoi « Open » : R1 alice–dave = 1/2-1/2';
        $this->assertJournal($expected);

        $root->nextRound($id, true);
        $expected[] = 'Tournoi « Open » : passage à la ronde 2';
        $this->assertJournal($expected);

        $root->close($id);
        $expected[] = 'Clôture du tournoi « Open »';
        $this->assertJournal($expected);

        $root->delete($id);
        $expected[] = 'Suppression du tournoi « Open »';
        $this->assertJournal($expected);

        self::assertSame([], $this->f->service->list());
        self::assertSame(array_fill(0, \count($expected), F::ROOT), array_column($this->f->journal->entries, 'actor'));
        self::assertSame(array_fill(0, \count($expected), true), $this->f->journalInTransaction, 'journal écrit dans la transaction');
    }

    public function testFailedAdminActionsAreNotJournaled(): void
    {
        $id = $this->f->tournament('Open', players: [F::ALICE]);
        $this->f->journal->entries = [];

        try {
            $this->f->as(F::ROOT)->start($id);
            self::fail('lancé avec un seul joueur');
        } catch (ConflictException $e) {
            self::assertSame('Au moins 2 joueurs inscrits sont nécessaires.', $e->getMessage());
        }
        self::assertSame([], $this->f->journal->entries);
    }

    public function testDeleteUnknownTournament(): void
    {
        $this->expectException(NotFoundException::class);
        $this->expectExceptionMessage('Tournoi introuvable.');

        $this->f->as(F::ROOT)->delete('t000000000042');
    }

    /** @return iterable<string, array{string, string, array<mixed>|null, ?int, string}> */
    public static function invalidCreations(): iterable
    {
        yield 'format inconnu' => ['Open', 'blitz', null, null, 'Format de tournoi inconnu.'];
        yield 'cadence nulle' => ['Open', 'round-robin', ['base' => 0, 'inc' => 0], null, 'Cadence invalide.'];
        yield 'cadence incomplète' => ['Open', 'round-robin', ['base' => 600], null, 'Cadence invalide.'];
        yield 'rondes du suisse' => ['Open', 'suisse', null, 20, 'Nombre de rondes : entre 2 et 15.'];
        yield 'nom trop court' => [' O ', 'round-robin', null, null, 'Nom trop court (2 caractères minimum).'];
        yield 'nom trop long' => [str_repeat('é', 61), 'round-robin', null, null, 'Nom trop long (60 caractères maximum).'];
    }

    /** @param array<mixed>|null $tc */
    #[DataProvider('invalidCreations')]
    public function testCreateValidation(string $name, string $format, ?array $tc, ?int $rounds, string $message): void
    {
        try {
            $this->f->as(F::ROOT)->create($name, '', $format, $tc, $rounds);
            self::fail('création acceptée : ' . $message);
        } catch (ValidationException $e) {
            self::assertSame($message, $e->getMessage());
        }
        self::assertSame([], $this->f->service->list());
        self::assertSame([], $this->f->journal->entries);
    }

    /** @return iterable<string, array{string}> */
    public static function takenNames(): iterable
    {
        yield 'casse' => ['OPEN'];
        yield 'espaces' => ['  open  '];
    }

    #[DataProvider('takenNames')]
    public function testDuplicateNameIsAConflict(string $name): void
    {
        $this->f->tournament('Open');
        $this->f->journal->entries = [];

        try {
            $this->f->as(F::ROOT)->create($name, '', 'round-robin', null, null);
            self::fail('nom en double accepté');
        } catch (ConflictException $e) {
            self::assertSame('Ce nom de tournoi est déjà pris.', $e->getMessage());
        }
        self::assertCount(1, $this->f->service->list());
        self::assertSame([], $this->f->journal->entries);
    }

    public function testFieldsAreValidatedBeforeTheDuplicateCheck(): void
    {
        $this->f->tournament('Open');

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Description trop longue (500 caractères maximum).');

        $this->f->as(F::ROOT)->create('Open', str_repeat('é', 501), 'round-robin', null, null);
    }

    /** @param list<string> $expected */
    private function assertJournal(array $expected): void
    {
        self::assertSame($expected, array_column($this->f->journal->entries, 'action'));
    }
}
