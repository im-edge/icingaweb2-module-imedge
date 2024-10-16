<?php

namespace Icinga\Module\Imedge\Controllers;

use gipfl\IcingaWeb2\CompatController;
use Icinga\Module\Imedge\Config\Defaults;
use Icinga\Module\Imedge\Web\Dashboard\ConfigurationDashboard;
use Icinga\Module\Imedge\Web\Dashboard\WebActions;

class InventoryController extends CompatController
{
    use SpecialActions;

    public function indexAction()
    {
        $this->setTitle(sprintf($this->translate('%s Inventory'), Defaults::APPLICATION_NAME));
        $this->addInventoryTab();
        $this->tabs()->activate('inventory');
        $this->content()->add(new ConfigurationDashboard(new WebActions())); // TODO: move actions elsewhere

        /** TODO: other column layout, e.g.: */
        /*$this->content()->add([
            Html::tag('div', ['class' => 'spalte-links', 'data-base-target' => 'mitte'], new ConfigurationDashboard($actions)),
            Html::tag('div', ['class' => ['spalte-mitte', 'container'], 'id' => 'mitte'], 'Mitte')
        ]);*/

    }
}
