<?php

declare(strict_types=1);

namespace LeCercle\Tests\Integration\Infra\Chat;

use LeCercle\Chat\Room;
use LeCercle\Infra\Chat\PdoRoomMessageRepository;
use LeCercle\Infra\Chat\PdoRoomRepository;
use LeCercle\Shared\ConflictException;
use LeCercle\Tests\Integration\DatabaseTestCase;

final class PdoRoomRepositoryTest extends DatabaseTestCase
{
    private function repo(): PdoRoomRepository
    {
        return new PdoRoomRepository(self::pdo());
    }

    private static function room(string $id, string $name, int $position = 0, ?string $createdBy = null): Room
    {
        return new Room($id, $name, 'Description', $position, false, $createdBy, new \DateTimeImmutable('2026-11-12 10:00:00.123', new \DateTimeZone('UTC')));
    }

    public function testNextIdHasPrefixAndTwelveHexDigits(): void
    {
        $repo = $this->repo();

        self::assertMatchesRegularExpression('/\Ar[0-9a-f]{12}\z/', $repo->nextId());
        self::assertNotSame($repo->nextId(), $repo->nextId());
    }

    public function testAddFindSaveRoundTrip(): void
    {
        $repo = $this->repo();
        $repo->add(self::room('r000000000001', 'Analyses', 3, 'u000000000001'));

        $found = $repo->find('r000000000001');
        self::assertNotNull($found);
        self::assertSame('Analyses', $found->name);
        self::assertSame('Description', $found->description);
        self::assertSame(3, $found->position);
        self::assertFalse($found->archived);
        self::assertSame('u000000000001', $found->createdBy);
        self::assertSame('2026-11-12 10:00:00.123', $found->createdAt->format('Y-m-d H:i:s.v'));

        $repo->save($found->renamed('Ouvertures')->withPosition(7)->withArchived(true));
        $saved = $repo->find('r000000000001');
        self::assertNotNull($saved);
        self::assertSame('Ouvertures', $saved->name);
        self::assertSame(7, $saved->position);
        self::assertTrue($saved->archived);
    }

    public function testNullCreatorAndMissingRoom(): void
    {
        $repo = $this->repo();
        $repo->add(self::room('general', 'Général'));

        self::assertNull($repo->find('general')?->createdBy);
        self::assertNull($repo->find('rinconnu00000'));
    }

    public function testAllIsOrderedByPositionThenId(): void
    {
        $repo = $this->repo();
        $repo->add(self::room('rb', 'B', 1));
        $repo->add(self::room('rc', 'C', 0));
        $repo->add(self::room('ra', 'A', 1));

        self::assertSame(['rc', 'ra', 'rb'], array_map(static fn(Room $r): string => $r->id, $repo->all()));
    }

    public function testNameUniquenessIgnoresCase(): void
    {
        $repo = $this->repo();
        $repo->add(self::room('r000000000001', 'Analyses'));

        self::assertTrue($repo->nameTaken('ANALYSES'));
        self::assertTrue($repo->nameTaken('analyses', 'rautre'));
        self::assertFalse($repo->nameTaken('Analyses', 'r000000000001'));
        self::assertFalse($repo->nameTaken('Débutants'));
    }

    public function testDuplicateNameOnAddIsAConflict(): void
    {
        $repo = $this->repo();
        $repo->add(self::room('r000000000001', 'Analyses'));

        $this->expectException(ConflictException::class);
        $this->expectExceptionMessage('Ce nom de salon est déjà pris.');
        // add() ne passe pas par nameTaken : c'est la clé unique, avec la collation de la colonne, qui refuse la collision d'accent.
        $repo->add(self::room('r000000000002', 'ANALYSÉS'));
    }

    public function testDuplicateNameOnSaveIsAConflict(): void
    {
        $repo = $this->repo();
        $repo->add(self::room('r000000000001', 'Analyses', 0));
        $repo->add(self::room('r000000000002', 'Débutants', 1));

        $this->expectException(ConflictException::class);
        $repo->save(self::room('r000000000002', 'analyses', 1));
    }

    public function testOtherDatabaseErrorsAreNotMappedToConflict(): void
    {
        $this->expectException(\PDOException::class);
        // Identifiant trop long pour VARCHAR(16) en mode strict : erreur 1406, pas un doublon.
        $this->repo()->add(self::room(str_repeat('x', 40), 'Long'));
    }

    public function testMaxPositionOnEmptyTableIsMinusOne(): void
    {
        $repo = $this->repo();
        self::assertSame(-1, $repo->maxPosition());

        $repo->add(self::room('ra', 'A', 4));
        $repo->add(self::room('rb', 'B', 9));
        self::assertSame(9, $repo->maxPosition());
    }

    public function testDeleteRemovesTheRoomAndItsMessagesOnly(): void
    {
        $repo = $this->repo();
        $repo->add(self::room('ra', 'A', 0));
        $repo->add(self::room('rb', 'B', 1));
        $messages = new PdoRoomMessageRepository(self::pdo());
        $at = new \DateTimeImmutable('2026-11-12 10:00:00', new \DateTimeZone('UTC'));
        $messages->add('ra', 'u1', 'un', $at);
        $messages->add('ra', 'u1', 'deux', $at);
        $messages->add('rb', 'u1', 'trois', $at);

        $repo->delete('ra');

        self::assertNull($repo->find('ra'));
        self::assertNotNull($repo->find('rb'));
        self::assertSame([], $messages->latest('ra', 50));
        self::assertCount(1, $messages->latest('rb', 50));
        $count = self::pdo()->query("SELECT COUNT(*) FROM room_messages WHERE room_id = 'ra'");
        self::assertNotFalse($count);
        self::assertSame(0, (int) $count->fetchColumn());
    }
}
