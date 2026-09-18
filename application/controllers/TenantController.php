<?php

namespace Icinga\Module\Imedge\Controllers;

use gipfl\IcingaWeb2\CompatController;
use gipfl\IcingaWeb2\Url;
use Icinga\Module\Imedge\Auth\Permission;
use Icinga\Module\Imedge\Web\Form\Configuration\TenantForm;
use Ramsey\Uuid\Uuid;

class TenantController extends CompatController
{
    use DbTrait;
    use TabsTraitImedge;

    public function indexAction(): void
    {
        $this->assertPermission(Permission::GLOBAL_ADMIN);

        $listUrl = 'imedge/tenants';
        $singleUrl = 'imedge/tenant';
        $uuid = $this->params->get('uuid');
        if ($uuid !== null && strlen($uuid)) {
            $uuid = Uuid::fromString($uuid);
            $this->addSingleTab($this->translate('Tenant'));
            $this->addTitle($this->translate('Modify Tenant'));
        } else {
            $uuid = null;
            $this->tabs()->add('devices', [
                'label' => $this->translate('Credentials'),
                'url'   => $listUrl,
            ]);
            $this->addSingleTab($this->translate('New Tenant'));
            $this->addTitle($this->translate('Add a new SNMP credential'));
        }

        $form = (new TenantForm($this->dbStore(), $uuid))
            ->on(TenantForm::ON_SUCCESS, function (TenantForm $form) use ($listUrl, $singleUrl) {
                $this->redirectNow($listUrl . '#!' . Url::fromPath($singleUrl, [
                        'uuid' => $form->getUuid()->toString()
                    ]));
            });
        $form->allowDelete($this->hasPermission(Permission::CREDENTIALS_DELETE));
        $this->content()->add($form->handleRequest($this->getServerRequest()));
        if ($form->hasBeenDeleted()) {
            $this->redirectNow($listUrl . '#!__CLOSE__');
        }
    }
}
