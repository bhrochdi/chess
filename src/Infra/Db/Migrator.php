<?php

declare(strict_types=1);

namespace LeCercle\Infra\Db;

use InvalidArgumentException;
use PDO;
use PDOException;
use RuntimeException;

/**
 * Applique dans l'ordre les migrations pas encore enregistrées. Le DDL MySQL
 * valide implicitement : aucune transaction n'est ouverte, une migration en
 * échec n'est pas enregistrée et arrête l'exécution.
 */
final class Migrator
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly string $directory,
        private readonly string $table = 'schema_migrations',
    ) {
        if (preg_match('/\A[a-z_]{1,64}\z/', $table) !== 1) {
            throw new InvalidArgumentException("Nom de table de migrations invalide : $table");
        }
    }

    /**
     * @return list<string> noms des migrations appliquées par cet appel
     */
    public function migrate(): array
    {
        $migrations = MigrationDirectory::load($this->directory);
        $this->pdo->exec(
            "CREATE TABLE IF NOT EXISTS `$this->table` (
                name VARCHAR(190) NOT NULL PRIMARY KEY,
                applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        );
        $done = $this->pdo->query("SELECT name FROM `$this->table`");
        $done = $done === false ? [] : $done->fetchAll(PDO::FETCH_COLUMN);
        $record = $this->pdo->prepare("INSERT INTO `$this->table` (name) VALUES (:name)");

        $applied = [];
        foreach ($migrations as $migration) {
            if (\in_array($migration->name, $done, true)) {
                continue;
            }
            try {
                foreach ($migration->statements as $statement) {
                    $this->pdo->exec($statement);
                }
            } catch (PDOException $e) {
                throw new RuntimeException("Migration $migration->name en échec : {$e->getMessage()}", 0, $e);
            }
            $record->execute(['name' => $migration->name]);
            $applied[] = $migration->name;
        }

        return $applied;
    }
}
