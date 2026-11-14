<?php

declare(strict_types=1);

namespace LeCercle\User;

use LeCercle\Shared\Access;
use LeCercle\Shared\AdminJournal;
use LeCercle\Shared\Clock;
use LeCercle\Shared\ConflictException;
use LeCercle\Shared\NotFoundException;
use LeCercle\Shared\Role;
use LeCercle\Shared\TransactionRunner;
use LeCercle\Shared\ValidationException;

/** Opérations d'administration sur les comptes : chacune est journalisée dans la même transaction. */
final class UserAdminService
{
    private const OPERATIONS = ['ban', 'unban', 'promote', 'demote', 'delete'];

    /** Un invité sans activité depuis 30 jours peut être purgé. */
    private const GUEST_TTL = 'P30D';

    public function __construct(
        private readonly UserRepository $users,
        private readonly Access $access,
        private readonly AdminJournal $journal,
        private readonly TransactionRunner $transactions,
        private readonly UserViews $views,
        private readonly Clock $clock,
    ) {}

    /**
     * Tous les comptes, e-mails compris : une seule lecture du dépôt.
     *
     * @return list<array<string, mixed>>
     */
    public function list(): array
    {
        $admin = $this->access->admin();

        return $this->views->users($this->users->all(), $admin);
    }

    public function update(string $targetId, string $op): void
    {
        $admin = $this->access->admin();
        if (!\in_array($op, self::OPERATIONS, true)) {
            throw new ValidationException('Opération inconnue.');
        }

        $this->transactions->run(function () use ($admin, $targetId, $op): void {
            $target = $this->users->find($targetId) ?? throw new NotFoundException('Utilisateur introuvable.');
            $self = $target->id === $admin->id;
            $name = $target->pseudo;

            switch ($op) {
                case 'ban':
                    if ($self) {
                        throw new ConflictException('Impossible de se suspendre soi-même.');
                    }
                    $this->users->save($target->withSuspended(true));
                    $action = 'Suspension de ' . $name;
                    break;
                case 'unban':
                    $this->users->save($target->withSuspended(false));
                    $action = 'Réactivation de ' . $name;
                    break;
                case 'promote':
                    if ($target->provider === Provider::Guest) {
                        throw new ConflictException('Un compte invité ne peut pas devenir administrateur.');
                    }
                    $this->users->save($target->withRole(Role::Admin));
                    $action = 'Rôle de ' . $name . ' → admin';
                    break;
                case 'demote':
                    if ($target->role === Role::Admin && $this->users->countAdmins() <= 1) {
                        throw new ConflictException('Impossible de rétrograder le dernier administrateur.');
                    }
                    $this->users->save($target->withRole(Role::User));
                    $action = 'Rôle de ' . $name . ' → user';
                    break;
                default:
                    if ($self) {
                        throw new ConflictException('Impossible de supprimer son propre compte.');
                    }
                    $this->users->delete($target->id);
                    $action = 'Suppression du compte ' . $name;
            }

            $this->journal->record($admin->id, $action);
        });
    }

    /** Supprime les invités inactifs ; leurs parties restent, sous « (compte supprimé) ». */
    public function purgeGuests(): int
    {
        $admin = $this->access->admin();
        $before = $this->clock->now()->sub(new \DateInterval(self::GUEST_TTL));

        return $this->transactions->run(function () use ($admin, $before): int {
            $ids = $this->users->inactiveGuestIds($before);
            foreach ($ids as $id) {
                $this->users->delete($id);
            }
            $this->journal->record($admin->id, 'Purge de ' . \count($ids) . ' compte(s) invité(s) inactif(s)');

            return \count($ids);
        });
    }
}
