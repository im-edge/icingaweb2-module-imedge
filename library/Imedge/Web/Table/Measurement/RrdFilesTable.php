<?php

namespace Icinga\Module\Imedge\Web\Table\Measurement;

use gipfl\IcingaWeb2\Link;
use gipfl\IcingaWeb2\Table\ZfQueryBasedTable;
use gipfl\Translation\TranslationHelper;
use Ramsey\Uuid\Uuid;

class RrdFilesTable extends ZfQueryBasedTable
{
    use TranslationHelper;

    protected $searchColumns = [
        'si.system_name',
        'rf.measurement_name',
        'rf.instance',
        'rf.filename',
    ];

    protected function renderRow($row): array
    {
        return [
            $row->system_name ?? '-',
            $row->measurement_name ?? '-',
            $this->linkInstanceToFile($row)
        ];
    }

    protected function getColumnsToBeRendered(): array
    {
        return [
            $this->translate('System'),
            $this->translate('Measurement'),
            $this->translate('Instance'),
        ];
    }

    protected function linkInstanceToFile($row): Link
    {
        return Link::create(
            $row->instance ?? '-',
            'imedge/measurement/file',
            ['uuid' => Uuid::fromBytes($row->file_uuid)->toString()]
        );
    }

    protected function prepareQuery()
    {
        return $this->db()->select()->from(['si' => 'snmp_system_info'], [
            'device_uuid'      => 'rf.device_uuid',
            'file_uuid'        => 'rf.uuid',
            'system_name'      => 'si.system_name',
            'measurement_name' => 'rf.measurement_name',
            'instance'         => 'rf.instance',
            'filename'         => 'rf.filename',
            // 'f.deleted',
        ])->joinRight(['rf' => 'rrd_file'], 'rf.device_uuid = si.uuid')
            ->order('si.system_name')
            ->order('rf.measurement_name')
            ->order('rf.instance')->limit(10);
    }
}
