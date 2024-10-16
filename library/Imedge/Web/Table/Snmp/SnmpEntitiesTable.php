<?php

namespace Icinga\Module\Imedge\Web\Table\Snmp;

use gipfl\IcingaWeb2\Table\ZfQueryBasedTable;
use IMEdge\Web\Data\Model\Entity;
use Ramsey\Uuid\UuidInterface;

class SnmpEntitiesTable extends ZfQueryBasedTable
{
    protected $searchColumns = [
        'e.name',
        'e.description',
        'e.model_name',
        'e.manufacturer_name',
    ];

    protected $systemUuid;

    protected function assemble()
    {
        $this->getAttributes()->add([
            'class' => 'xxlimited-row-height-table',
            'style' => 'width: 100%; max-width: unset'
        ]);
    }

    public function filterSystemUuid(UuidInterface $systemUuid)
    {
        // TODO: reuqire this
        $this->systemUuid = $systemUuid;

        return $this;
    }

    public function getColumnsToBeRendered()
    {
        return array(
            $this->translate('Index'),
            $this->translate('Name'),
// $this->translate('Alias'),
            $this->translate('Description'),
            // $this->translate('Asset'),
            $this->translate('Parent'),
            $this->translate('Class'),
            $this->translate('RelPos'),
            $this->translate('Manufacturer'),
            $this->translate('Model'),
// $this->translate('HardwareRev'),
// $this->translate('FirmwareRev'),
// $this->translate('SoftwareRev'),
            $this->translate('Serial'),
// $this->translate('FRU'),
        );
    }

    public function renderRow($row)
    {
        if (!in_Array($row->entity_index, [1, 300, 695, 696])) {
            // return null;
        }
        return static::row((array) $row);
    }

    public function prepareQuery()
    {
        // TODO: if no agent -> join all of them, add columns
        return $this->db()->select()
            ->from(['e' => Entity::TABLE], [
                'entity_index'            => 'e.entity_index',
                'name'                => 'e.name',
// 'alias'               => 'e.alias',
                'description'            => 'description',
                // 'asset_id'               => 'asset_id', -> TODO: check, we have no related data?!
                'parent_index'           => 'parent_index',
                'class'                  => 'class',
                'relative_position'      => 'relative_position',
                'manufacturer_name'      => 'manufacturer_name',
                'model_name'             => 'model_name',
// 'revision_hardware'      => 'revision_hardware',
// 'revision_firmware'      => 'revision_firmware',
// 'revision_software'      => 'revision_software',
                'serial_number'          => 'serial_number',
// 'field_replaceable_unit' => 'field_replaceable_unit',
            ])
            ->where('e.device_uuid = ?', $this->systemUuid->getBytes())
            ->where('e.class != ?', 'sensor')
            ->limit(100)
            ->order('e.parent_index')
            ->order('e.relative_position')
            ->order('e.entity_index');
    }
}
