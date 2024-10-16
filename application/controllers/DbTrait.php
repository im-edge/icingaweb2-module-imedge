<?php

namespace Icinga\Module\Imedge\Controllers;

use gipfl\IcingaWeb2\Url;
use gipfl\ZfDb\Adapter\Adapter as ZfDb;
use gipfl\ZfDbStore\ZfDbStore;
use Icinga\Application\Config;
use Icinga\Exception\ConfigurationError;
use Icinga\Module\Imedge\Config\Defaults;
use Icinga\Module\Imedge\Config\IcingaResource;
use Icinga\Module\Imedge\Db\ZfDbConnectionFactory;

trait DbTrait
{
    protected static ?ZfDb $db = null;
    protected static ?ZfDbStore $dbStore = null;

    protected function db(): ZfDb
    {
        return self::$db ??= self::newDbConnection();
    }

    private function newDbConnection(): ZfDb
    {
        if ($name = Config::module(Defaults::MODULE_NAME)->get('db', 'resource')) {
            $db = ZfDbConnectionFactory::connection(
                IcingaResource::requireResourceConfig($name, 'db')
            );
            assert($db instanceof ZfDb);
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
