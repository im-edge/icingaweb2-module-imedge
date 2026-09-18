<?php

namespace Icinga\Module\Imedge\Controllers;

use gipfl\IcingaWeb2\CompatController;
use gipfl\IcingaWeb2\Link;
use Icinga\Module\Imedge\Auth\Permission;
use Icinga\Module\Imedge\Web\Table\Inventory\TenantsTable;

class TenantsController extends CompatController
{
    use DbTrait;
    use TabsTraitImedge;

    public function indexAction(): void
    {
        $this->assertPermission(Permission::GLOBAL_ADMIN);
        $this->imedgeTabs()->activate('tenants');
        $this->addTitle('Tenants');
        $this->actions()->add(
            Link::create($this->translate('Add'), 'imedge/tenant', null, [
                'class' => 'icon-plus',
                'data-base-target' => '_next',
            ])
        );
        $table = new TenantsTable($this->db());
        $table->renderTo($this);
    }
}
