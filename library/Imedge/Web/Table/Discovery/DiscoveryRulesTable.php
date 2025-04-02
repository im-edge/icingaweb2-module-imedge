<?php

namespace Icinga\Module\Imedge\Web\Table\Discovery;

use gipfl\IcingaWeb2\Link;
use gipfl\IcingaWeb2\Table\ZfQueryBasedTable;
use Ramsey\Uuid\Uuid;

class DiscoveryRulesTable extends ZfQueryBasedTable
{
    public function getColumnsToBeRendered()
    {
        return [
            $this->translate('Rule'),
        ];
    }

    public function renderRow($row)
    {
        return static::row([
            Link::create($row->label, 'imedge/discovery/rule', [
                'uuid' => Uuid::fromBytes($row->uuid)->toString()
            ])
         ]);
    }

    public function prepareQuery()
    {
        return $this->db()->select()
            ->from(['sdr' => 'snmp_discovery_rule'], [
                'uuid'           => 'sdr.uuid',
                'label'     => 'sdr.label',
                'implementation' => 'sdr.implementation',
            ])
            ->limit(50)
            ->order('sdr.label');
    }
}
