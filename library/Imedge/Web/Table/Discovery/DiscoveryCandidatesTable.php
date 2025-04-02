<?php

namespace Icinga\Module\Imedge\Web\Table\Discovery;

use gipfl\IcingaWeb2\Link;
use gipfl\IcingaWeb2\Table\ZfQueryBasedTable;
use Ramsey\Uuid\Uuid;

class DiscoveryCandidatesTable extends ZfQueryBasedTable
{
    public function getColumnsToBeRendered()
    {
        return [
            $this->translate('Address'),
            $this->translate('System'),
            $this->translate('Rule'),
            $this->translate('Node'),
        ];
    }

    public function renderRow($row)
    {
        return static::row([
            'udp://' . inet_ntop($row->ip_address) . ':' . $row->snmp_port,
            Link::create($row->system_name, 'imedge/discovery/candidate', [
                'uuid' => Uuid::fromBytes($row->uuid)->toString()
            ]),
            $row->rule_label,
            $row->datanode_label,
         ]);
    }

    public function prepareQuery()
    {
        return $this->db()->select()
            ->from(['sdc' => 'snmp_discovery_candidate'], [
                'uuid'           => 'sdc.uuid',
                'ip_address'     => 'sdc.ip_address',
                'snmp_port'      => 'sdc.snmp_port',
                'state'          => 'sdc.state',
                'ts_last_check'  => 'sdc.ts_last_check',
                'rule_label'     => 'sdr.label',
                'datanode_label' => 'd.label',
                'system_name'    => 'si.system_name',
            ])->join(
                ['sdr' => 'snmp_discovery_rule'],
                'sdc.discovery_rule_uuid = sdr.uuid',
                []
            )->join(
                ['d' => 'datanode'],
                'sdc.datanode_uuid = d.uuid',
                []
            )->joinLeft(
                ['si' => 'snmp_system_info'],
                'sdc.uuid = si.uuid',
                []
            )
            ->limit(50)
            // ->where('d.label = ?', 'php81-bull.lxd')
            ->order('sdc.ip_address');
//            ->order('a.ip_address');
    }
}
