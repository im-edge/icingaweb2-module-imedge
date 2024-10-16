<?php

namespace Icinga\Module\Imedge\Web\Table\Measurement;

use gipfl\IcingaWeb2\Link;
use gipfl\IcingaWeb2\Table\Extension\MultiSelect;
use gipfl\IcingaWeb2\Table\ZfQueryBasedTable;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

class MeasurementsTable extends ZfQueryBasedTable
{
    use MultiSelect;

    protected $searchColumns = [
        'f.measurement_name',
        'f.instance',
    ];
    protected ?UuidInterface $deviceUuid = null;

    public function filterDevice(UuidInterface $deviceUuid)
    {
        $this->deviceUuid = $deviceUuid;
    }

    public function assemble()
    {
        /*
        $this->enableMultiSelect(
            'metrics/files/combine',
            'imedge/measurement/file',
            ['uuid']
        );
        */
    }

    protected function renderRow($row): array
    {
        if (!$this->deviceUuid) {
            return [
                $this->rowLink(Uuid::fromBytes($row->device_uuid)->toString(), $row),
                $row->measurement_name ?? '-',
                $row->instance ?? '-',
            ];
        }
        if ($row->instance) {
            return [
                $row->measurement_name ?? '-',
                $this->rowLink($row->instance, $row),
            ];
        }
        return [
            $this->rowLink($row->measurement_name, $row),
            $row->instance ?? '-',
        ];
    }

    protected function rowLink(string $label, $row): Link
    {
        return Link::create($label, 'imedge/measurement/file', ['uuid' => Uuid::fromBytes($row->uuid)->toString()]);
    }

    protected function prepareQuery()
    {
        $query = $this->db()->select()->from(['f' => 'rrd_file'], [
            'f.uuid',
            'f.device_uuid',
            'a.label',
            'a.ip_address',
            'a.snmp_port',
            'f.measurement_name',
            'f.instance',
        ])->joinLeft(['a' => 'snmp_agent'], 'f.device_uuid = a.agent_uuid');
        if ($this->deviceUuid) {
            $query->where('f.device_uuid = ?', $this->deviceUuid->getBytes());
        } else {
            $query->order('f.device_uuid');
        }
        $query->order('f.measurement_name')->order('f.instance');

        return $query;
    }
}
