<?php

declare(strict_types=1);

namespace LeCercle\Infra\Db;

use InvalidArgumentException;

/**
 * Lit les fichiers « NNN-nom.sql » d'un dossier, triés par numéro.
 * Une instruction se termine par un « ; » en fin de ligne.
 */
final class MigrationDirectory
{
    private const FILE_NAME = '/\A(\d{3})-[a-z0-9]+(?:-[a-z0-9]+)*\.sql\z/';

    /**
     * @return list<Migration>
     */
    public static function load(string $directory): array
    {
        if (!is_dir($directory)) {
            throw new InvalidArgumentException("Dossier de migrations introuvable : $directory");
        }

        $byNumber = [];
        foreach (glob($directory . '/*.sql') ?: [] as $path) {
            $fileName = basename($path);
            if (preg_match(self::FILE_NAME, $fileName, $match) !== 1) {
                throw new InvalidArgumentException("Nom de migration invalide : $fileName");
            }
            if (isset($byNumber[$match[1]])) {
                throw new InvalidArgumentException("Numéro de migration en double : $match[1]");
            }
            $byNumber[$match[1]] = new Migration(basename($fileName, '.sql'), self::statements($path));
        }
        ksort($byNumber, SORT_STRING);

        return array_values($byNumber);
    }

    /**
     * @return list<string>
     */
    private static function statements(string $path): array
    {
        $sql = file_get_contents($path);
        if ($sql === false) {
            throw new InvalidArgumentException("Migration illisible : $path");
        }

        $statements = [];
        foreach (preg_split('/;[ \t]*(?:\R|\z)/', $sql) ?: [] as $piece) {
            if (!self::isBlank($piece)) {
                $statements[] = trim($piece);
            }
        }

        return $statements;
    }

    private static function isBlank(string $piece): bool
    {
        foreach (preg_split('/\R/', $piece) ?: [] as $line) {
            $line = trim($line);
            if ($line !== '' && !str_starts_with($line, '--')) {
                return false;
            }
        }

        return true;
    }
}
