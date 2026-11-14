<?php

declare(strict_types=1);

namespace LeCercle\User;

use LeCercle\Shared\Access;
use LeCercle\Shared\Clock;
use LeCercle\Shared\ConflictException;
use LeCercle\Shared\ForbiddenException;
use LeCercle\Shared\Member;
use LeCercle\Shared\Millis;
use LeCercle\Shared\Role;
use LeCercle\Shared\SessionLogin;
use LeCercle\Shared\TooManyRequestsException;
use LeCercle\Shared\TransactionRunner;
use LeCercle\Shared\ValidationException;

/** Inscription, connexion, déconnexion et consultation du compte courant. */
final class AuthService
{
    public const GUEST_ATTEMPTS = 5;

    public const BAD_CREDENTIALS = 'Identifiant ou mot de passe incorrect.';

    public const TOO_MANY = 'Trop de tentatives de connexion. Réessaie dans quelques minutes.';

    /** Échecs tolérés dans la fenêtre, par identifiant puis par adresse du client. */
    private const MAX_PER_IDENTIFIER = 5;

    private const MAX_PER_ADDRESS = 20;

    public const WINDOW_MS = 900_000;

    /** Empreinte bcrypt (coût 10) d'un mot de passe inconnu : sert à dépenser le même calcul pour un identifiant inexistant. */
    private const DUMMY_HASH = '$2y$10$//qkiFPmYYiuJVWLcGVtvOSaxRTIz/.xrvqcU3Up3p0xNkjEgINp2';

    public function __construct(
        private readonly UserRepository $users,
        private readonly PasswordHasher $hasher,
        private readonly SessionLogin $session,
        private readonly Access $access,
        private readonly Clock $clock,
        private readonly TransactionRunner $transactions,
        private readonly UserViews $views,
        private readonly LoginAttempts $attempts,
    ) {}

    /** @return array<string, mixed> */
    public function register(string $pseudo, string $email, string $password): array
    {
        $pseudo = UserFields::pseudo($pseudo);
        $email = UserFields::email($email);
        $password = UserFields::password($password);
        $hash = $this->hasher->hash($password);

        $user = $this->transactions->run(function () use ($pseudo, $email, $hash): User {
            if ($this->users->pseudoTaken($pseudo)) {
                throw new ConflictException('Ce pseudo est déjà pris.');
            }
            if ($this->users->emailTaken($email)) {
                throw new ConflictException('Un compte existe déjà avec cet e-mail.');
            }
            $role = $this->users->countRegistered() === 0 ? Role::Admin : Role::User;
            $user = User::registered($this->users->nextId(), $pseudo, $email, $hash, $role, $this->clock->now());
            $this->users->add($user);

            return $user;
        });
        $this->session->logIn($user->id);

        return $this->views->user($user, self::member($user));
    }

    /** @return array<string, mixed> */
    public function login(string $identifier, string $password, string $address = ''): array
    {
        $identifier = strtolower(trim($identifier));
        if ($identifier === '' || $password === '') {
            throw new ValidationException('Renseigne ton identifiant et ton mot de passe.');
        }
        $user = $this->users->findByLogin($identifier);
        // Un compte existant est compté par son id : passer du pseudo à l'e-mail ne remet pas le compteur à zéro.
        // Clés hachées : la table des tentatives ne garde ni identifiant ni adresse en clair.
        $limits = ['id:' . hash('sha256', $user === null ? $identifier : $user->id) => self::MAX_PER_IDENTIFIER];
        if ($address !== '') {
            $limits['ip:' . hash('sha256', $address)] = self::MAX_PER_ADDRESS;
        }
        $now = Millis::of($this->clock->now());
        // Vérifié avant le mot de passe : un compte bloqué ne dit pas si le mot de passe essayé est le bon.
        foreach ($limits as $key => $max) {
            if ($this->attempts->failuresSince($key, $now - self::WINDOW_MS + 1) >= $max) {
                throw new TooManyRequestsException(self::TOO_MANY);
            }
        }

        $usable = $user !== null && $user->canLogInWithPassword();
        // Un identifiant inconnu coûte le même calcul qu'un mot de passe erroné.
        $hash = $usable ? (string) $user->passwordHash : self::DUMMY_HASH;
        $verified = $this->hasher->verify($password, $hash);
        // bcrypt ignore tout après 72 octets : un mot de passe plus long ne peut pas être celui d'un compte.
        if ($user === null || !$usable || !$verified || strlen($password) > 72) {
            foreach (array_keys($limits) as $key) {
                $this->attempts->record($key, $now);
            }
            throw new ForbiddenException(self::BAD_CREDENTIALS);
        }
        // Seul l'identifiant est remis à zéro : un compte valide ne doit pas effacer les essais d'une adresse.
        $this->attempts->clear(array_key_first($limits));
        if ($user->suspended) {
            throw new ForbiddenException(Access::SUSPENDED);
        }
        if ($this->hasher->needsRehash($hash)) {
            $user = $user->withPasswordHash($this->hasher->hash($password));
            $this->users->save($user);
        }
        $this->session->logIn($user->id);

        return $this->views->user($user, self::member($user));
    }

    /**
     * Compte invité : pseudo choisi, ou « Invite_ » suivi de 8 caractères hexadécimaux de l'identifiant.
     *
     * @return array<string, mixed>
     */
    public function guest(?string $pseudo): array
    {
        if ($this->access->viewer() !== null) {
            throw new ConflictException('Tu es déjà connecté.');
        }
        $chosen = $pseudo === null ? null : UserFields::pseudo($pseudo);

        $user = $this->transactions->run(function () use ($chosen): User {
            if ($chosen !== null) {
                if ($this->users->pseudoTaken($chosen)) {
                    throw new ConflictException('Ce pseudo est déjà pris.');
                }
                $id = $this->users->nextId();
                $name = $chosen;
            } else {
                for ($attempt = 0; $attempt < self::GUEST_ATTEMPTS; ++$attempt) {
                    $id = $this->users->nextId();
                    $name = 'Invite_' . substr($id, -8);
                    if (!$this->users->pseudoTaken($name)) {
                        break;
                    }
                    $name = null;
                }
                if ($name === null) {
                    throw new ConflictException('Pseudo d’invité indisponible, réessaie.');
                }
            }
            $user = User::guest($id, $name, $this->clock->now());
            $this->users->add($user);

            return $user;
        });
        $this->session->logIn($user->id);

        return $this->views->user($user, self::member($user));
    }

    /**
     * Le premier compte inscrit qui le demande devient administrateur tant qu'il n'y en a aucun.
     *
     * @return array<string, mixed>
     */
    public function claimAdmin(): array
    {
        $member = $this->access->member();
        if ($member->guest) {
            throw new ForbiddenException('Réservé aux comptes inscrits.');
        }

        $user = $this->transactions->run(function () use ($member): User {
            if ($this->users->countAdmins() > 0) {
                throw new ConflictException('Un administrateur existe déjà.');
            }
            $current = $this->users->find($member->id) ?? throw new ForbiddenException(Access::LOGIN_REQUIRED);
            $admin = $current->withRole(Role::Admin);
            $this->users->save($admin);

            return $admin;
        });

        return $this->views->user($user, self::member($user));
    }

    public function logout(): void
    {
        $this->session->logOut();
    }

    /** @return array<string, mixed>|null */
    public function me(): ?array
    {
        $viewer = $this->access->viewer();
        $user = $viewer === null ? null : $this->users->find($viewer->id);

        return $user === null ? null : $this->views->user($user, $viewer);
    }

    private static function member(User $user): Member
    {
        return new Member($user->id, $user->pseudo, $user->role, $user->suspended, $user->provider === Provider::Guest);
    }
}
