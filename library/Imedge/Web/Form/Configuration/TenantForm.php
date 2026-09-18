<?php

namespace Icinga\Module\Imedge\Web\Form\Configuration;

use gipfl\Translation\TranslationHelper;
use Icinga\Module\Imedge\Web\Form\UuidObjectForm;
use IMEdge\Web\Data\Model\Tenant;

class TenantForm extends UuidObjectForm
{
    use TranslationHelper;

    protected string $modelClass = Tenant::class;
    protected $keyProperty = 'uuid';

    protected function assemble()
    {
        $this->addFormElements();
        $this->addButtons();
    }

    protected function addFormElements()
    {
        $this->addElement('text', 'name', [
            'label' => $this->translate('Tenant name'),
            'required' => true,
            'description' => $this->translate('Unique Identifier for this Tenant (e.g. modern-net)')
        ]);
        $this->addElement('text', 'label', [
            'label' => $this->translate('Label'),
            'required' => true,
            'description' => $this->translate('Label for visualization purposes (e.g. Modern Networking Ltd.)')
        ]);
    }

    protected function getObjectLabel()
    {
        if ($this->hasElement('label')) {
            return $this->getElementValue('label', $this->translate('A new SNMP Tenant'));
        }

        return $this->translate('SNMP Tenant');
    }
}
