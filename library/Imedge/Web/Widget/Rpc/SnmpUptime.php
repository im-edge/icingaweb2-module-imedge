<?php

namespace Icinga\Module\Imedge\Web\Widget\Rpc;

use DateTime;

class SnmpUptime
{
    public static function getDateTime($value): DateTime
    {
        $time = new DateTime();
        $time->setTimestamp(round(microtime(true) - (float) $value - 0.3));
        return $time;
    }
}
