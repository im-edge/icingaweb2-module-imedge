<?php

namespace Icinga\Module\Imedge\Snmp;

use InvalidArgumentException;

class ContextSpecificError
{
    protected const NO_SUCH_OBJECT = 'no_such_object';     // 0
    protected const NO_SUCH_INSTANCE = 'no_such_instance'; // 1
    protected const END_OF_MIB_VIEW = 'end_of_mib_view';   // 2

    public static function intToName(int $value): string
    {
        switch ($value) {
            case 0:
                return self::NO_SUCH_OBJECT;
            case 1:
                return self::NO_SUCH_INSTANCE;
            case 2:
                return self::END_OF_MIB_VIEW;
            default:
                throw new InvalidArgumentException($value . ' is no a valid context-specific value');
        }
    }
}
/*
 *                             self::NO_SUCH_OBJECT   => 'No such object',
                            self::NO_SUCH_INSTANCE => 'No such instance',
                            self::END_OF_MIB_VIEW  => 'End of MIB view',

 */
