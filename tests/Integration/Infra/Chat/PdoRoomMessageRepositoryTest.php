<?php

declare(strict_types=1);

namespace LeCercle\Tests\Integration\Infra\Chat;

use LeCercle\Chat\ChatMessage;
use LeCercle\Chat\Room;
use LeCercle\Infra\Chat\PdoRoomMessageRepository;
use LeCercle\Infra\Chat\PdoRoomRepository;
use LeCercle\Tests\Integration\DatabaseTestCase;

final class PdoRoomMessageRepositoryTest extends DatabaseTestCase
{
    private function at(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('2026-11-12 10:00:00.456', new \DateTimeZone('UTC'));
    }

    private function repo(): PdoRoomMessageRepository
    {
        $rooms = new PdoRoomRepository(self::pdo());
        foreach (['ra' => 'A', 'rb' => 'B'] as $id => $name) {
            $rooms->add(new Room($id, $name, '', 0, false, null, $this->at()));
        }

        return new PdoRoomMessageRepository(self::pdo());
    }

    /**
     * @param list<ChatMessage> $messages
     * @return list<string>
     */
    private static function texts(array $messages): array
    {
        return array_map(static fn(ChatMessage $m): string => $m->text, $messages);
    }

    public function testAddReturnsIncreasingIdsAndTheStoredMessage(): void
    {
        $repo = $this->repo();
        $first = $repo->add('ra', 'u1', 'Bonjour', $this->at());
        $second = $repo->add('ra', 'u2', 'Salut', $this->at());

        self::assertGreaterThan($first->id, $second->id);
        self::assertSame('u1', $first->authorId);
        self::assertSame('Bonjour', $first->text);
        self::assertSame('2026-11-12 10:00:00.456', $first->createdAt->format('Y-m-d H:i:s.v'));
    }

    public function testLatestReturnsTheLastOnesOldestFirst(): void
    {
        $repo = $this->repo();
        $repo->add('ra', 'u1', 'un', $this->at());
        $repo->add('ra', 'u1', 'deux', $this->at());
        $repo->add('ra', 'u1', 'trois', $this->at());

        self::assertSame(['deux', 'trois'], self::texts($repo->latest('ra', 2)));
        self::assertSame(['un', 'deux', 'trois'], self::texts($repo->latest('ra', 50)));
    }

    public function testFindDeleteClearAreScopedToTheRoom(): void
    {
        $repo = $this->repo();
        $a = $repo->add('ra', 'u1', 'a1', $this->at());
        $repo->add('ra', 'u1', 'a2', $this->at());
        $b = $repo->add('rb', 'u1', 'b1', $this->at());

        self::assertSame('a1', $repo->find('ra', $a->id)?->text);
        self::assertNull($repo->find('ra', $b->id), 'un message d\'un autre salon n\'est pas trouvé');

        $repo->delete('ra', $b->id);
        self::assertSame(['b1'], self::texts($repo->latest('rb', 10)), 'delete ne touche pas un autre salon');

        $repo->delete('ra', $a->id);
        self::assertSame(['a2'], self::texts($repo->latest('ra', 10)));

        $repo->clear('ra');
        self::assertSame([], $repo->latest('ra', 10));
        self::assertSame(['b1'], self::texts($repo->latest('rb', 10)), 'clear ne touche pas un autre salon');
    }
}
