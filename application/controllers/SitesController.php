<?php

namespace Icinga\Module\Imedge\Controllers;

use gipfl\IcingaWeb2\CompatController;
use gipfl\IcingaWeb2\Link;
use Icinga\Module\Imedge\Web\Table\Inventory\SitesTable;

class SitesController extends CompatController
{
    use DbTrait;
    use SpecialActions;

    public function indexAction()
    {
        $this->addInventoryTab();
        $this->addSingleTab($this->translate('Sites'));
        $this->actions()->add(Link::create($this->translate('Create'), 'imedge/site', [], [
            'data-base-target' => '_next',
            'class'            => 'icon-plus'
        ]));
        $this->addTitle($this->translate('Site Overview'));
        (new SitesTable($this->db()))->renderTo($this);
    }
}
