<?php

declare(strict_types=1);

namespace LeCercle\Tests\Support;

use LeCercle\Shared\Member;
use LeCercle\Shared\Members;
use LeCercle\User\Provider;
use LeCercle\User\User;
use LeCercle\User\UserRepository;

/** Double de test : lecture des membres au-dessus du dépôt d'utilisateurs, pour que session et dépôt restent cohérents. */
final class UserRepositoryMembers implements Members
{
    public function __construct(private readonly UserRepository $users) {}

    public function find(string $id): ?Member
    {
        $user = $this->users->find($id);

        return $user === null ? null : self::member($user);
    }

    public function findByPseudo(string $pseudo): ?Member
    {
        $needle = strtolower($pseudo);
        foreach ($this->users->all() as $user) {
            if (strtolower($user->pseudo) === $needle) {
                return self::member($user);
            }
        }

        return null;
    }

    /** @param array<int, string> $ids */
    public function findMany(array $ids): array
    {
        $found = [];
        foreach (array_values(array_unique($ids)) as $id) {
            $member = $this->find($id);
            if ($member !== null) {
                $found[$id] = $member;
            }
        }

        return $found;
    }

    private static function member(User $user): Member
    {
        return new Member($user->id, $user->pseudo, $user->role, $user->suspended, $user->provider === Provider::Guest);
    }
}
