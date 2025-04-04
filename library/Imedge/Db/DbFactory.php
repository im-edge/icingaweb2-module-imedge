<?php

namespace Icinga\Module\Imedge\Db;

use gipfl\ZfDb\Adapter\Pdo\PdoAdapter as Db;
use Icinga\Application\Config;
use Icinga\Exception\ConfigurationError;
use Icinga\Module\Imedge\Config\Defaults;
use Icinga\Module\Imedge\Config\IcingaResource;

class DbFactory
{
    protected static ?Db $db = null;

    public static function db(): Db
    {
        if (self::$db === null) {
            if ($name = Config::module(Defaults::MODULE_NAME)->get('db', 'resource')) {
                $db = ZfDbConnectionFactory::connection(
                    IcingaResource::requireResourceConfig($name, 'db')
                );
                assert($db instanceof Db);
                self::$db = $db;
            } else {
                throw new ConfigurationError(sprintf(
                    'Found no "%s" in the "[%s]" section of %s',
                    'resource',
                    'db',
                    Config::module(Defaults::MODULE_NAME)->getConfigFile()
                ));
            }
        }
        return self::$db;
    }
}
