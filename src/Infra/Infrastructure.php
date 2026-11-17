<?php

declare(strict_types=1);

namespace LeCercle\Infra;

use LeCercle\Infra\Admin\PdoAdminJournal;
use LeCercle\Infra\Db\DbConfig;
use LeCercle\Infra\Db\PdoFactory;
use LeCercle\Infra\Db\PdoThrottle;
use LeCercle\Infra\Db\PdoTransactionRunner;
use LeCercle\Infra\Session\PhpSession;
use LeCercle\Infra\User\PdoMembers;
use LeCercle\Shared\Access;
use LeCercle\Shared\AdminJournal;
use LeCercle\Shared\Clock;
use LeCercle\Shared\Members;
use LeCercle\Shared\Throttle;
use LeCercle\Shared\TransactionRunner;
use PDO;

/**
 * Partie commune de la racine de composition : chaque point d'entrée api/*.php
 * construit ses services à partir de ces briques, créées à la demande une
 * seule fois. La configuration n'est lue qu'au premier accès à la base.
 */
final class Infrastructure
{
    private ?PDO $pdo = null;
    private ?Clock $clock = null;
    private ?PhpSession $session = null;
    private ?TransactionRunner $transactions = null;
    private ?Members $members = null;
    private ?Access $access = null;
    private ?AdminJournal $adminJournal = null;
    private ?Throttle $throttle = null;

    /**
     * @param \Closure(): DbConfig $config
     * @param bool                 $https  requête reçue en HTTPS : le cookie de session est alors « Secure »
     */
    public function __construct(private readonly \Closure $config, private readonly bool $https = false) {}

    public static function fromGlobals(string $configFile): self
    {
        return new self(static fn(): DbConfig => DbConfig::load(getenv(), $configFile), self::isHttps($_SERVER));
    }

    /**
     * Vrai si Apache a reçu la requête en HTTPS. Aucun mandataire n'est placé devant l'application :
     * les en-têtes X-Forwarded-* ne sont pas lus, un client pourrait les forger.
     *
     * @param array<mixed> $server
     */
    public static function isHttps(array $server): bool
    {
        $https = $server['HTTPS'] ?? null;

        return (\is_string($https) && $https !== '' && strtolower($https) !== 'off')
            || (string) ($server['SERVER_PORT'] ?? '') === '443';
    }

    public function pdo(): PDO
    {
        return $this->pdo ??= PdoFactory::create(($this->config)());
    }

    public function clock(): Clock
    {
        return $this->clock ??= new SystemClock();
    }

    /** À typer CurrentUser ou SessionLogin là où la session est consommée. */
    public function session(): PhpSession
    {
        return $this->session ??= new PhpSession($this->https);
    }

    public function transactions(): TransactionRunner
    {
        return $this->transactions ??= new PdoTransactionRunner($this->pdo());
    }

    public function members(): Members
    {
        return $this->members ??= new PdoMembers($this->pdo());
    }

    public function access(): Access
    {
        return $this->access ??= new Access($this->session(), $this->members());
    }

    public function adminJournal(): AdminJournal
    {
        return $this->adminJournal ??= new PdoAdminJournal($this->pdo(), $this->clock());
    }

    public function throttle(): Throttle
    {
        return $this->throttle ??= new PdoThrottle($this->pdo());
    }
}
