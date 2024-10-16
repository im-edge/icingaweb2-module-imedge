<?php

namespace Icinga\Module\Imedge\Controllers;

use gipfl\IcingaWeb2\CompatController;
use gipfl\IcingaWeb2\Link;
use Icinga\Module\Imedge\Web\Form\Filter\NodeFilterForm;
use Icinga\Module\Imedge\Web\Table\Inventory\SnmpDevicesTable;

class SnmpController extends CompatController
{
    use DbTrait;
    use SpecialActions;
    use WebClientInfo;

    public function init()
    {
        // Hint: we intentionally do not call our parent's init() method
    }
    /**
     * @api
     */
    public function devicesAction(): void
    {
        $this->setAutorefreshInterval(6);
        $this->addInventoryTab();
        $this->addSingleTab($this->translate('Devices'));
        $this->addTitle($this->translate('SNMP Devices'));
        $this->actions()->add([
            Link::create($this->translate('Add'), 'inventory/snmp/device', null, [
                'class' => 'icon-plus',
                'data-base-target' => '_main',
            ]),
            Link::create($this->translate('Export'), 'inventory/data/export', null, [
                'class' => 'icon-download',
                'target' => '_blank',
            ]),
        ]);
        $table = new SnmpDevicesTable($this->db());
        $form = new NodeFilterForm($this->db());
        $form->setAction((string) $this->getOriginalUrl()->without('datanode'));
        $form->handleRequest($this->getServerRequest());
        $this->content()->add($form);
        if ($uuid = $form->getUuid()) {
            $table->getQuery()->where('si.datanode_uuid = ?', $uuid->getBytes());
        }

        $table->renderTo($this);
    }
}
