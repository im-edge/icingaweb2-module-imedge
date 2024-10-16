<?php

namespace Icinga\Module\Imedge\Controllers;

use gipfl\IcingaWeb2\CompatController;
use Icinga\Module\Imedge\Web\Table\ShowCase\SampleMacAddressTable;

class ShowcaseController extends CompatController
{
    use DbTrait;

    public function init()
    {
        $this->tabs()->add('mac-addresses', [
            'label' => $this->translate('MAC Addresses / Vendors'),
            'url'   => 'inventory/lab/mac-addresses',
        ])->activate($this->getRequest()->getActionName());
    }

    public function macAddressesAction()
    {
        $this->addTitle($this->translate('MAC Addresses - Sample Lookup'));
        $this->content()->add(new SampleMacAddressTable($this->db()));
    }
}
