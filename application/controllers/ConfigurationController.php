<?php

namespace Icinga\Module\Imedge\Controllers;

use gipfl\IcingaWeb2\CompatController;
use gipfl\Web\Widget\Hint;
use Icinga\Module\Imedge\Web\Form\Configuration\ChooseDbResourceForm;
use IMEdge\Web\Rpc\IMEdgeClient;
use ipl\Html\Html;

class ConfigurationController extends CompatController
{
    use DbTrait;

    public function indexAction(): void
    {
        $this->addSingleTab($this->translate('Configuration'));
        switch ($this->params->get('error')) {
            case 'db-resource-not-set':
                $this->configureDbResource();
                return;
            case 'db-missing-schema':
                $this->missingDbSchema();
                return;
        }
    }

    protected function missingDbSchema(): void
    {
        $this->setAutorefreshInterval(5);
        $this->addTitle($this->translate('IMEdge Database Schema'));
        try {
            $client = new IMEdgeClient();
            // $client->request()
        } catch (\Exception $e) {

        }
        if ($this->hasSchema()) {
            $this->redirectNow('imedge/inventory');
        }
        $this->content()->add([
            Hint::error($this->translate('No database schema has been created in the configured database')),
            Html::tag('p', $this->translate(
                'Please start the IMEdge daemon with the "inventory" feature.'
                . ' It will create all required database tables.'
            ))
        ]);
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
