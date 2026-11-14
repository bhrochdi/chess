<?php

declare(strict_types=1);

namespace LeCercle\Chat;

use LeCercle\Shared\Access;
use LeCercle\Shared\AdminJournal;
use LeCercle\Shared\Clock;
use LeCercle\Shared\ConflictException;
use LeCercle\Shared\MemberRef;
use LeCercle\Shared\Members;
use LeCercle\Shared\NotFoundException;
use LeCercle\Shared\TransactionRunner;
use LeCercle\Shared\ValidationException;

/** Salons de discussion : lecture et envoi côté utilisateur, gestion et modération côté administrateur. */
final class RoomService
{
    public const MESSAGES_LIMIT = 80;

    public function __construct(
        private readonly RoomRepository $rooms,
        private readonly RoomMessageRepository $messages,
        private readonly Access $access,
        private readonly Clock $clock,
        private readonly MessageViews $views,
        private readonly AdminJournal $journal,
        private readonly TransactionRunner $transactions,
        private readonly Members $members,
    ) {}

    /** @return list<array<string, mixed>> les salons non archivés */
    public function list(): array
    {
        return $this->views->rooms(array_values(array_filter(
            $this->rooms->all(),
            static fn(Room $r): bool => !$r->archived,
        )));
    }

    /** @return list<array<string, mixed>> tous les salons, pour l'administration */
    public function listAll(): array
    {
        $this->access->admin();

        return $this->views->rooms($this->rooms->all());
    }

    /** @return list<array<string, mixed>> */
    public function messages(string $roomId): array
    {
        $room = $this->room($roomId);
        if ($room->archived) {
            $viewer = $this->access->viewer();
            if ($viewer === null || !$viewer->isAdmin() || $viewer->suspended) {
                throw new NotFoundException('Salon introuvable.');
            }
        }

        return $this->views->list($this->messages->latest($room->id, self::MESSAGES_LIMIT));
    }

    /** @return array<string, mixed> */
    public function send(string $roomId, string $text): array
    {
        $author = $this->access->member();
        $room = $this->room($roomId);
        if ($room->archived) {
            throw new ConflictException('Ce salon est archivé.');
        }
        $message = $this->messages->add($room->id, $author->id, MessageText::clean($text), $this->clock->now());

        return $this->views->one($message);
    }

    /** @return array<string, mixed> */
    public function create(string $name, string $description): array
    {
        $admin = $this->access->admin();
        $name = RoomFields::name($name);
        $description = RoomFields::description($description);

        return $this->transactions->run(function () use ($admin, $name, $description): array {
            $this->assertNameFree($name, null);
            $room = new Room(
                $this->rooms->nextId(),
                $name,
                $description,
                $this->rooms->maxPosition() + 1,
                false,
                $admin->id,
                $this->clock->now(),
            );
            $this->rooms->add($room);
            $this->journal->record($admin->id, "Création du salon « {$room->name} »");

            return $this->views->room($room);
        });
    }

    /** @return array<string, mixed> */
    public function rename(string $roomId, string $name): array
    {
        $admin = $this->access->admin();
        $name = RoomFields::name($name);

        return $this->transactions->run(function () use ($admin, $roomId, $name): array {
            $room = $this->room($roomId);
            if ($room->name === $name) {
                return $this->views->room($room);
            }
            $this->assertNameFree($name, $room->id);
            $renamed = $room->renamed($name);
            $this->rooms->save($renamed);
            $this->journal->record($admin->id, "Salon « {$room->name} » renommé « {$name} »");

            return $this->views->room($renamed);
        });
    }

    /** @return list<array<string, mixed>> tous les salons, dans le nouvel ordre */
    public function move(string $roomId, string $direction): array
    {
        $admin = $this->access->admin();
        if ($direction !== 'up' && $direction !== 'down') {
            throw new ValidationException('Direction inconnue.');
        }

        return $this->transactions->run(function () use ($admin, $roomId, $direction): array {
            $room = $this->room($roomId);
            $all = $this->rooms->all();
            $index = array_search($room->id, array_map(static fn(Room $r): string => $r->id, $all), true);
            if ($index === false) {
                throw new \LogicException("Salon {$room->id} absent de la liste des salons.");
            }
            $target = $direction === 'up' ? $index - 1 : $index + 1;
            if (isset($all[$target])) {
                [$all[$index], $all[$target]] = [$all[$target], $all[$index]];
                foreach ($all as $position => $candidate) {
                    if ($candidate->position !== $position) {
                        $this->rooms->save($candidate->withPosition($position));
                    }
                }
                $this->journal->record($admin->id, "Salon « {$room->name} » déplacé");
            }

            return $this->views->rooms($this->rooms->all());
        });
    }

    /** @return array<string, mixed> */
    public function setArchived(string $roomId, bool $archived): array
    {
        $admin = $this->access->admin();

        return $this->transactions->run(function () use ($admin, $roomId, $archived): array {
            $room = $this->room($roomId);
            if ($room->archived === $archived) {
                return $this->views->room($room);
            }
            if ($archived && $room->isDefault()) {
                throw new ConflictException('Le salon « Général » reste toujours visible.');
            }
            $updated = $room->withArchived($archived);
            $this->rooms->save($updated);
            $this->journal->record($admin->id, ($archived ? 'Archivage' : 'Désarchivage') . " du salon « {$room->name} »");

            return $this->views->room($updated);
        });
    }

    public function delete(string $roomId): void
    {
        $admin = $this->access->admin();
        $this->transactions->run(function () use ($admin, $roomId): void {
            $room = $this->room($roomId);
            if ($room->isDefault()) {
                throw new ConflictException('Le salon « Général » ne peut pas être supprimé.');
            }
            $this->rooms->delete($room->id);
            $this->journal->record($admin->id, "Suppression du salon « {$room->name} »");
        });
    }

    public function deleteMessage(string $roomId, int $messageId): void
    {
        $admin = $this->access->admin();
        $this->transactions->run(function () use ($admin, $roomId, $messageId): void {
            $room = $this->room($roomId);
            $message = $this->messages->find($room->id, $messageId) ?? throw new NotFoundException('Message introuvable.');
            $author = $this->members->find($message->authorId);
            $who = $author === null ? MemberRef::DELETED_PSEUDO : "({$author->pseudo})";
            $this->messages->delete($room->id, $message->id);
            $this->journal->record($admin->id, "Suppression d’un message du salon « {$room->name} » {$who}");
        });
    }

    public function clear(string $roomId): void
    {
        $admin = $this->access->admin();
        $this->transactions->run(function () use ($admin, $roomId): void {
            $room = $this->room($roomId);
            $this->messages->clear($room->id);
            $this->journal->record($admin->id, "Salon « {$room->name} » vidé");
        });
    }

    private function room(string $roomId): Room
    {
        return $this->rooms->find($roomId) ?? throw new NotFoundException('Salon introuvable.');
    }

    private function assertNameFree(string $name, ?string $exceptId): void
    {
        if ($this->rooms->nameTaken($name, $exceptId)) {
            throw new ConflictException('Ce nom de salon est déjà pris.');
        }
    }
}
