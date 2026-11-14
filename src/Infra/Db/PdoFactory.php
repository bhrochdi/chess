<?php

declare(strict_types=1);

namespace LeCercle\Infra\Db;

use PDO;

/**
 * Ouvre la connexion PDO : exceptions, requêtes préparées natives, session en UTC.
 * Messages d'erreur du serveur forcés en anglais pour la session : les dépôts
 * lisent le nom de la clé violée dans « … for key '…' », absent quand le
 * serveur est configuré dans une autre langue (lc_messages=fr_FR sous WampServer).
 * Mode SQL strict (valeur par défaut de MySQL 8) imposé pour la même raison :
 * WampServer livre sql_mode="" et tronquerait en silence une valeur trop longue.
 */
final class PdoFactory
{
    public const SQL_MODE = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,'
        . 'ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION';

    public static function create(DbConfig $config): PDO
    {
        return new PDO($config->dsn(), $config->user, $config->password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET time_zone = '+00:00', lc_messages = 'en_US', sql_mode = '" . self::SQL_MODE . "'",
        ]);
    }
}
