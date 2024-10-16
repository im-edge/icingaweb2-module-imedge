<?php

namespace Icinga\Module\Imedge\Web\Table\Inventory;

use gipfl\IcingaWeb2\Link;
use gipfl\IcingaWeb2\Table\ZfQueryBasedTable;
use Ramsey\Uuid\Uuid;

class SitesTable extends ZfQueryBasedTable
{
    protected $searchColumns = [
        's.site_name' // TODO: -> label?
    ];

    protected ?string $lastLocation = null;

    protected function renderLocationIfNew(string $location)
    {
        if ($this->lastLocation !== $location) {
            $this->nextHeader()->add(
                $this::th($location, [
                    'colspan' => 2,
                    'class'   => 'table-header-day'
                ])
            );

            $this->lastLocation = $location;
            $this->nextBody();
        }
    }

    protected function renderRow($row)
    {
        $this->renderLocationIfNew($row->city ?? '-');
        $uuid = Uuid::fromBytes($row->uuid);
        return $this::row([
            Link::create($row->site_name, 'imedge/site', [
                'uuid' => $uuid->toString()
            ]),
        ]);
    }

    protected function prepareQuery()
    {
        return $this->db()->select()
            ->from(['s' => 'inventory_site'], [
                's.uuid',
                's.site_name',
                's.address_uuid',
                'city' => "CONCAT(ia.city_name, ' (', ia.country_code, ')')",
            ])->join(['ia' => 'inventory_address'], 's.address_uuid = ia.uuid', [])
            ->limit(20)
            ->order('ia.country_code')->order('ia.city_name')->order('s.site_name');
    }
}
