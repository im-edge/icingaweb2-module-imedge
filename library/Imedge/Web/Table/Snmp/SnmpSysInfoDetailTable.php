<?php

namespace Icinga\Module\Imedge\Web\Table\Snmp;

use gipfl\Web\Table\NameValueTable;
use IMEdge\Web\Data\Model\SnmpSystemInfo;

class SnmpSysInfoDetailTable extends NameValueTable
{
    public function __construct(SnmpSystemInfo $info)
    {
        $this->addNameValuePairs([
            'System Name'  => $info->get('system_name'),
            'Location'     => $info->get('system_location'),
            'Contact'      => $info->get('system_contact'),
            'Description'  => $info->get('system_description'),
            'Services'     => $info->get('system_services'),
            'Engine ID'    => self::hexFormat($info->get('system_engine_id')),
            'Engine Boot Count' => $info->get('system_engine_boot_count'),
            'Engine Boot Time' => $info->get('system_engine_boot_time'),
            'OID'          => $info->get('system_oid'),
            'Max Message Size' => $info->get('system_engine_max_message_size'),


            // Linux shows 0x30 -> "0"?!
            'Bridge Address'  => self::hexFormat($info->get('dot1d_base_bridge_address')),
        ]);
    }

    protected static function hexFormat(?string $string): string
    {
        return $string === null ? '-' : '0x' . bin2hex($string);
    }
}
