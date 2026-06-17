<?php

namespace Icinga\Module\Imedge\Snmp;

use stdClass;

class ResultHelper
{
    public static function flipTable(stdClass $repeaters, ?array $columns = null): array
    {
        $result = [];
        foreach ((array) $repeaters as $requestedOid => $replies) {
            $label = $columns[$requestedOid] ?? $requestedOid;
            foreach ($replies as $index => $varBind) {
                $result[$index] ??= (object) [];
                $result[$index]->$label = $varBind;
            }
        }

        return $result;
    }
}
