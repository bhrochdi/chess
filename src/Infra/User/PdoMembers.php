<?php

declare(strict_types=1);

namespace LeCercle\Infra\User;

use LeCercle\Shared\Member;
use LeCercle\Shared\Members;
use LeCercle\Shared\Role;

/** Comptes lus dans la table `users`. */
final class PdoMembers implements Members
{
    private const COLUMNS = 'SELECT id, pseudo, role, banned, provider FROM users';

    public function __construct(private readonly \PDO $pdo) {}

    public function find(string $id): ?Member
    {
        return $this->fetch(' WHERE id = ?', [$id])[0] ?? null;
    }

    public function findByPseudo(string $pseudo): ?Member
    {
        // Insensible à la casse grâce à la collation de la colonne (utf8mb4_unicode_ci).
        return $this->fetch(' WHERE pseudo = ?', [$pseudo])[0] ?? null;
    }

    public function findMany(array $ids): array
    {
        $ids = array_values(array_unique($ids));
        if ($ids === []) {
            return [];
        }
        $placeholders = implode(', ', array_fill(0, \count($ids), '?'));
        $found = [];
        foreach ($this->fetch(" WHERE id IN ($placeholders)", $ids) as $member) {
            $found[$member->id] = $member;
        }

        return $found;
    }

    /**
     * @param list<string> $params
     *
     * @return list<Member>
     */
    private function fetch(string $where, array $params): array
    {
        $stmt = $this->pdo->prepare(self::COLUMNS . $where);
        $stmt->execute($params);

        $members = [];
        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $members[] = new Member(
                (string) $row['id'],
                (string) $row['pseudo'],
                Role::tryFrom((string) $row['role']) ?? Role::User,
                (int) $row['banned'] === 1,
                $row['provider'] === 'guest',
            );
        }

        return $members;
    }
}
