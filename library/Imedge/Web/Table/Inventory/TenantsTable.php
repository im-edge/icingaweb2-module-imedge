<?php

namespace Icinga\Module\Imedge\Web\Table\Inventory;

use gipfl\IcingaWeb2\Link;
use gipfl\IcingaWeb2\Table\ZfQueryBasedTable;
use Ramsey\Uuid\Uuid;

class TenantsTable extends ZfQueryBasedTable
{
    protected $searchColumns = [
        'label',
    ];
    public function getColumnsToBeRendered(): array
    {
        return array(
            $this->translate('Tenant name'),
        );
    }

    public function renderRow($row)
    {
        return static::row([
            Link::create($row->label, 'imedge/tenant', [
                'uuid' => Uuid::fromBytes($row->uuid)->toString()
            ]),
        ]);
    }

    public function prepareQuery()
    {
        return $this->db()->select()
            ->from('tenant', [
                'uuid',
                'label',
            ])->order('label');
    }
}
