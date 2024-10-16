<?php

namespace Icinga\Module\Imedge\Web\Widget\Rpc;

class SnmpValue
{
    public static function getReadableSnmpValue($value)
    {
        if ($value === null) {
            return '(null - not a datatype)';
        }
        if (is_bool($value)) {
            return '(' . ($value ? 'true' : 'false') . ' - not a datatype)';
        }
        if (! is_object($value) || ! property_exists($value, 'type')) {
            return var_export($value, true);
        }

        switch ($value->type) {
            case 'null':
                return '(null)';
            case 'octet_string':
                if (substr($value->value, 0, 2) === '0x') {
                    $bin = hex2bin(substr($value->value, 2));
                } else {
                    $bin = $value->value;
                }

                if (ctype_print($bin) || mb_check_encoding($bin, 'UTF-8')) {
                    return $bin;
                }

                return '0x' . bin2hex($bin);
            case 'oid':
            case 'gauge32':
            case 'counter32':
            case 'counter64':
            case 'ip_address':
                return $value->value;
            case 'time_ticks':
                return $value->value / 100;
            case 'opaque':
                return $value->value; // for float example see UCD-SNMP-MIB::laLoadFloat
            case 'context_specific':
                // Probably "No such oid" or similar
                switch ($value->value) {
                    case 0:
                        return '[ no such object ]';
                    case 1:
                        return '[ no such instance ]';
                    case 2:
                        return '[ end of mib view ]';
                    default:
                        return json_encode($value);
                }
            default:
                return $value;
        }
    }
}
