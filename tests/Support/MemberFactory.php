<?php

declare(strict_types=1);

namespace LeCercle\Tests\Support;

use LeCercle\Shared\Member;
use LeCercle\Shared\Role;

/** Fabrique de membres pour les tests. */
final class MemberFactory
{
    public static function user(string $id = 'u000000000001', string $pseudo = 'alice'): Member
    {
        return new Member($id, $pseudo, Role::User, false, false);
    }

    public static function admin(string $id = 'u000000000009', string $pseudo = 'root'): Member
    {
        return new Member($id, $pseudo, Role::Admin, false, false);
    }

    public static function suspended(string $id = 'u000000000002', string $pseudo = 'bob'): Member
    {
        return new Member($id, $pseudo, Role::User, true, false);
    }

    public static function guest(string $id = 'u000000000003', string $pseudo = 'Invite_ab12'): Member
    {
        return new Member($id, $pseudo, Role::User, false, true);
    }
}
