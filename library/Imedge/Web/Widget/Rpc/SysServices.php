<?php

namespace Icinga\Module\Imedge\Web\Widget\Rpc;

class SysServices
{
    protected const LAYERS = [
        1 => 'Physical',
        2 => 'Datalink', // bridges
        3 => 'Internet', // routers
        4 => 'End-To-End', // TCP
        7 => 'Application', // SMTP ..
    ];

    protected const BIT_VALUE = [ // 2 ^ (LAYER - 1)
        2 ** 0 => 'Physical',
        2 ** 1 => 'Datalink (Bridge)', // bridges
        2 ** 2 => 'Internet (Router)', // routers
        2 ** 3 => 'End-To-End', // TCP
        2 ** 6 => 'Application', // SMTP ..
    ];

    public static function getList(int $sysServices): array
    {
        $services = [];
        foreach (self::BIT_VALUE as $value => $name) {
            if (($sysServices & $value) === $value) {
                $services[] = $name;
            }
        }

        return $services;
    }
}
