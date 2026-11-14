<?php

declare(strict_types=1);

namespace LeCercle\Infra\User;

use LeCercle\Infra\Db\SqlTime;
use LeCercle\Shared\ConflictException;
use LeCercle\Shared\Millis;
use LeCercle\Shared\Role;
use LeCercle\User\Provider;
use LeCercle\User\User;
use LeCercle\User\UserRepository;

/** Comptes stockés dans la table `users`. */
final class PdoUserRepository implements UserRepository
{
    private const SELECT = 'SELECT id, pseudo, email, provider, role, pass_hash, banned, created_at, last_seen_at FROM users';

    public function __construct(private readonly \PDO $pdo) {}

    public function nextId(): string
    {
        return 'u' . bin2hex(random_bytes(6));
    }

    public function add(User $user): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO users (id, pseudo, email, provider, role, pass_hash, banned, created_at, last_seen_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
        );
        try {
            $stmt->execute([
                $user->id,
                $user->pseudo,
                $user->email,
                $user->provider->value,
                $user->role->value,
                $user->passwordHash,
                $user->suspended ? 1 : 0,
                Millis::of($user->createdAt),
                $user->lastSeenAt === null ? null : SqlTime::format($user->lastSeenAt),
            ]);
        } catch (\PDOException $e) {
            // Deux inscriptions simultanées peuvent passer les contrôles du service : la base tranche.
            throw match ($e->errorInfo[1] ?? null) {
                1062 => match (self::duplicateKey($e)) {
                    'uniq_pseudo' => new ConflictException('Ce pseudo est déjà pris.', 0, $e),
                    'uniq_email' => new ConflictException('Un compte existe déjà avec cet e-mail.', 0, $e),
                    default => $e,
                },
                // Deux premières inscriptions simultanées se verrouillent mutuellement (countRegistered() FOR UPDATE).
                1213 => new ConflictException('Inscription simultanée, réessaie.', 0, $e),
                default => $e,
            };
        }
    }

    public function save(User $user): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE users SET pseudo = ?, email = ?, role = ?, pass_hash = ?, banned = ? WHERE id = ?',
        );
        $stmt->execute([
            $user->pseudo,
            $user->email,
            $user->role->value,
            $user->passwordHash,
            $user->suspended ? 1 : 0,
            $user->id,
        ]);
    }

    public function find(string $id): ?User
    {
        return $this->fetch(' WHERE id = ?', [$id])[0] ?? null;
    }

    public function findByLogin(string $identifier): ?User
    {
        // Le pseudo est comparé en minuscules ; l'e-mail l'est par la collation de la colonne.
        return $this->fetch(' WHERE LOWER(pseudo) = ? OR email = ? LIMIT 1', [$identifier, $identifier])[0] ?? null;
    }

    public function pseudoTaken(string $pseudo): bool
    {
        return $this->exists('pseudo', $pseudo);
    }

    public function emailTaken(string $email): bool
    {
        return $this->exists('email', $email);
    }

    /**
     * Comptes inscrits, lignes verrouillées : sérialise deux premières inscriptions
     * simultanées. provider n'est pas indexé, donc le verrou couvre toute la table,
     * même quand elle ne contient que des invités.
     */
    public function countRegistered(): int
    {
        return $this->scalar("SELECT COUNT(*) FROM users WHERE provider <> 'guest' FOR UPDATE");
    }

    /**
     * Administrateurs non suspendus uniquement, lignes verrouillées (FOR
     * UPDATE) : le garde-fou « dernier administrateur » et claimAdmin() ne
     * doivent pas se croiser dans deux transactions concurrentes.
     */
    public function countAdmins(): int
    {
        return $this->scalar("SELECT COUNT(*) FROM users WHERE role = 'admin' AND banned = 0 FOR UPDATE");
    }

    public function delete(string $id): void
    {
        $this->pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$id]);
    }

    public function all(): array
    {
        return $this->fetch(' ORDER BY created_at, id', []);
    }

    public function seenSince(\DateTimeImmutable $since): array
    {
        return $this->fetch(' WHERE last_seen_at >= ? ORDER BY pseudo', [SqlTime::format($since)]);
    }

    public function inactiveGuestIds(\DateTimeImmutable $before): array
    {
        // last_seen_at est un DATETIME UTC, created_at un BIGINT en millisecondes.
        $stmt = $this->pdo->prepare(
            "SELECT id FROM users WHERE provider = 'guest'
             AND (last_seen_at < ? OR (last_seen_at IS NULL AND created_at < ?)) ORDER BY id",
        );
        $stmt->execute([SqlTime::format($before), Millis::of($before)]);

        return array_values(array_map('strval', $stmt->fetchAll(\PDO::FETCH_COLUMN)));
    }

    public function touch(string $id, \DateTimeImmutable $now): void
    {
        $this->pdo->prepare('UPDATE users SET last_seen_at = ? WHERE id = ?')->execute([SqlTime::format($now), $id]);
    }

    /**
     * Nom de la clé violée, lu en fin de message (« … for key 'users.uniq_pseudo' ») :
     * la valeur dupliquée, citée plus tôt dans le message, ne peut pas l'imiter.
     */
    private static function duplicateKey(\PDOException $e): ?string
    {
        $message = $e->errorInfo[2] ?? $e->getMessage();

        return \is_string($message) && preg_match("/for key '(?:users\\.)?(\\w+)'\\z/", $message, $m) === 1 ? $m[1] : null;
    }

    private function exists(string $column, string $value): bool
    {
        // Insensible à la casse grâce à la collation utf8mb4_unicode_ci.
        $stmt = $this->pdo->prepare("SELECT 1 FROM users WHERE $column = ? LIMIT 1");
        $stmt->execute([$value]);

        return $stmt->fetchColumn() !== false;
    }

    private function scalar(string $sql): int
    {
        $stmt = $this->pdo->query($sql);
        if ($stmt === false) {
            throw new \RuntimeException('Requête de comptage impossible.');
        }

        return (int) $stmt->fetchColumn();
    }

    /**
     * @param list<string> $params
     *
     * @return list<User>
     */
    private function fetch(string $suffix, array $params): array
    {
        $stmt = $this->pdo->prepare(self::SELECT . $suffix);
        $stmt->execute($params);

        $users = [];
        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $users[] = new User(
                (string) $row['id'],
                (string) $row['pseudo'],
                $row['email'] === null ? null : (string) $row['email'],
                Provider::tryFrom((string) $row['provider']) ?? Provider::Local,
                Role::tryFrom((string) $row['role']) ?? Role::User,
                $row['pass_hash'] === null ? null : (string) $row['pass_hash'],
                (int) $row['banned'] === 1,
                Millis::toDateTime((int) $row['created_at']),
                $row['last_seen_at'] === null ? null : SqlTime::parse((string) $row['last_seen_at']),
            );
        }

        return $users;
    }
}
