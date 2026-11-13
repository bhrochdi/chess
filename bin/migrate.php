<?php

declare(strict_types=1);

// Applique les migrations de sql/migrations : php bin/migrate.php

require __DIR__ . '/../vendor/autoload.php';

use LeCercle\Infra\Db\DbConfig;
use LeCercle\Infra\Db\Migrator;
use LeCercle\Infra\Db\PdoFactory;

try {
    $pdo = PdoFactory::create(DbConfig::load(getenv(), __DIR__ . '/../api/config.php'));
    $applied = (new Migrator($pdo, __DIR__ . '/../sql/migrations'))->migrate();
    foreach ($applied as $name) {
        echo "Appliquée : $name\n";
    }
    if ($applied === []) {
        echo "Base à jour.\n";
    }
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage() . "\n");
    exit(1);
}
