<?php

namespace Icinga\Module\Imedge\Controllers;

use gipfl\DbMigration\Migrations;
use gipfl\IcingaWeb2\Url;
use gipfl\ZfDb\Adapter\Pdo\PdoAdapter;
use gipfl\ZfDbStore\ZfDbStore;
use Icinga\Application\Config;
use Icinga\Application\Modules\Module;
use Icinga\Exception\ConfigurationError;
use Icinga\Module\Imedge\Config\Defaults;
use Icinga\Module\Imedge\Config\IcingaResource;
use Icinga\Module\Imedge\Db\ZfDbConnectionFactory;

trait DbTrait
{
    protected static ?PdoAdapter $db = null;
    protected static ?ZfDbStore $dbStore = null;

    protected function db(): PdoAdapter
    {
        return self::$db ??= self::newDbConnection();
    }

    private function newDbConnection(): PdoAdapter
    {
        if ($name = $this->getDbResourceName()) {
            $db = $this->getDbAdapter($name);
            $db->getConnection(); // triggers error, in case the connection doesn't work
            $migrations= new Migrations($db, Module::get('imedge')->getBaseDir() . '/schema');
            if (! $migrations->hasSchema()) {
                $this->redirectToConfigError('db-missing-schema');
            }
        } elseif ($this->isApiRequest()) {
            throw new ConfigurationError(sprintf(
                'Found no "%s" in the "[%s]" section of %s',
                'resource',
                'db',
                Config::module(Defaults::MODULE_NAME)->getConfigFile()
            ));
        } else {
            $this->redirectToConfigError('db-resource-not-set');
        }

        return $db;
    }

    protected function hasSchema(): bool
    {
        if ($name = $this->getDbResourceName()) {
            return $this->getMigrations($this->getDbAdapter($name))->hasSchema();
        }

        return false;
    }

    protected function getMigrations(PdoAdapter $db): Migrations
    {
        return new Migrations($db, Module::get('imedge')->getBaseDir() . '/schema');
    }

    private function getDbAdapter(string $resourceName): PdoAdapter
    {
        return ZfDbConnectionFactory::connection(
            IcingaResource::requireResourceConfig($resourceName, 'db')
        );
    }

    private function getDbResourceName(): ?string
    {
        return Config::module(Defaults::MODULE_NAME)->get('db', 'resource');
    }

    /**
     * @return never-return
     */
    protected function redirectToConfigError(string $errorRef): void
    {
        $this->redirectNow(Url::fromPath('imedge/configuration', [
            'error' => $errorRef
        ]));
    }

    protected function dbStore(): ZfDbStore
    {
        return self::$dbStore ??= new ZfDbStore($this->db());
    }

    protected function isApiRequest(): bool
    {
        return false;
    }
}
