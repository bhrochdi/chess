<?php

declare(strict_types=1);

namespace LeCercle\Chat;

/** Stockage des salons. */
interface RoomRepository
{
    /** Nouvel identifiant : « r » suivi de 12 caractères hexadécimaux. */
    public function nextId(): string;

    /** @return list<Room> triés par position, puis par identifiant */
    public function all(): array;

    public function find(string $id): ?Room;

    /** Insensible à la casse ; sous MySQL, la collation ignore aussi les accents. */
    public function nameTaken(string $name, ?string $exceptId = null): bool;

    public function add(Room $room): void;

    public function save(Room $room): void;

    /** Les messages du salon partent avec lui. */
    public function delete(string $id): void;

    /** -1 quand il n'y a aucun salon. */
    public function maxPosition(): int;
}
