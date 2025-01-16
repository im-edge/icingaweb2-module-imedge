<?php

namespace Icinga\Module\Imedge\Web\Table\Snmp;

use gipfl\IcingaWeb2\Icon;
use gipfl\IcingaWeb2\Link;
use gipfl\IcingaWeb2\Table\Extension\MultiSelect;
use gipfl\IcingaWeb2\Table\ZfQueryBasedTable;
use IMEdge\Web\Data\Lookup\IpToCountryLiteLookup;
use IMEdge\Web\Data\Widget\IpAddress;
use IMEdge\Web\Device\DeviceVendor;
use ipl\Html\Html;
use Ramsey\Uuid\Uuid;

class SnmpDevicesTable extends ZfQueryBasedTable
{
    use MultiSelect;

    protected $searchColumns = [
        'si.system_name',
        'si.system_description',
        'sa.label',
    ];

    // protected DeviceShapeLookup $deviceLookup;
    protected IpToCountryLiteLookup $ipLookup;

    protected function assemble()
    {
        $this->enableMultiSelect('imedge/snmp/device', 'imedge/devices', ['uuid']);
        $this->getAttributes()->add('class', ['one-column-table', 'table-with-state']);
        // $this->deviceLookup = new DeviceShapeLookup();
        $this->ipLookup = new IpToCountryLiteLookup($this->db());
    }

    protected function getRowIconForState(?string $state): Icon
    {
        switch ($state) {
            case 'reachable':
                return Icon::create('ok', [
                    'title' => $this->translate('Ok'), // TODO -> reachable?
                ]);
            case 'failing':
                return Icon::create('cancel', [
                    'title' => $this->translate('Unreachable (via SNMP)'),
                ]);
            case 'disabled':
                return Icon::create('off', [
                    'title' => $this->translate('Disabled'),
                ]);
            case 'pending':
                return Icon::create('plus', [
                    'class' => 'state-pending',
                    'title' => $this->translate('Discovered, not monitored yet'),
                ]);
            default:
                return Icon::create('help', ['class' => 'state-unknown']);
        }
    }

    protected static function getRowStateForState(?string $state): string
    {
        switch ($state) {
            case 'reachable':
                return 'state-ok';
            case 'failing':
                return 'state-critical';
            case 'disabled':
            case 'pending':
                return 'state-pending';
            default:
                return 'state-unknown';
        }
    }

    public function renderRow($row)
    {
        /*
        $device = $this->deviceLookup->lookup($row->manufacturer_name, $row->model_name);
        if ($device) {
            $deviceContainer = Html::tag('span', [
                'style' => 'display: inline-block; width: 100%; min-width: 16em; max-height: 10em; overflow: hidden;'
                    . ' min-height: 3em;'
            ], $device);
        } else {
            $deviceContainer = null;
        }
        */
        return static::row([
            [
                // $this->getRowIconForState($row->state),
                DeviceVendor::getVendorLogo($row),
                Link::create($row->label, 'imedge/snmp/preferred-device-view', [
                    'uuid' => Uuid::fromBytes($row->uuid)->toString()
                ], [
                    'style' => 'font-weight: bold;'
                ]),
                Html::tag('br'),
                $row->ip_address
                    ? (new IpAddress($row->ip_address, $this->ipLookup))->add(':' . $row->snmp_port)
                    : null,
            ]
        ], [
            'class' => self::getRowStateForState($row->state)
        ]);
    }

/*
| system_services                | tinyint(3) unsigned | YES  |     | NULL    |       |
| system_oid                     | varchar(255)        | YES  |     | NULL    |       |
| system_engine_id               | varbinary(64)       | YES  |     | NULL    |       |
| system_engine_boot_count       | bigint(20) unsigned | YES  |     | NULL    |       |
| system_engine_boot_time        | bigint(20) unsigned | YES  |     | NULL    |       |
| system_engine_max_message_size | int(10) unsigned    | YES  |     | NULL    |       |
| dot1d_base_bridge_address      | varbinary(6)        | YES  |     | NULL    |       |
*/
    public function prepareQuery()
    {
        return $this->db()->select()
            ->from(['si' => 'snmp_system_info'], [
                'state'              => 'th.state',
                'uuid'               => 'sa.agent_uuid',
                'label'              => 'COALESCE(sa.label, si.system_name)',
                'system_name'        => 'si.system_name',
                'system_description' => 'si.system_description',
                'system_contact'     => 'si.system_contact',
                'system_location'    => 'si.system_location',
                'system_oid'         => 'si.system_oid',
                'ip_address'         => 'sa.ip_address',
                'snmp_port'          => 'sa.snmp_port',
            ])->joinRight(['sa' => 'snmp_agent'], 'si.uuid = sa.agent_uuid', [])
            ->joinLeft(
                ['th' => 'snmp_target_health'],
                'si.uuid = th.uuid',
                []
            )
            ->limit(15)
            ->order("COALESCE(si.system_name, 'ZZZZZZZZZZZZ')");
    }
}
