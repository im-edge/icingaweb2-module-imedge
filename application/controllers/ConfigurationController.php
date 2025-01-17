<?php

namespace Icinga\Module\Imedge\Controllers;

use gipfl\IcingaWeb2\CompatController;
use gipfl\Web\Widget\Hint;
use Icinga\Module\Imedge\Web\Form\Configuration\ChooseDbResourceForm;

class ConfigurationController extends CompatController
{
    public function indexAction(): void
    {
        $this->addSingleTab($this->translate('Configuration'));
        switch ($this->params->get('error')) {
            case 'db-resource-not-set':
                $this->configureDbResource();
                return;
        }
    }

    protected function configureDbResource(): void
    {
        $this->addTitle($this->translate('IMEdge Database Configuration'));
        if (!$this->hasPermission('imedge/admin')) {
            $this->content()->add(Hint::error('No database resource has been configured yet. Please ask an'
                . ' Administrator to complete your config'));
            return;
        }
        $form = new ChooseDbResourceForm($this->Config());
        $form->on($form::ON_SUCCESS, fn () =>  $this->redirectNow($this->url()));
        $form->handleRequest($this->getServerRequest());
        $this->content()->add($form);
    }
}
