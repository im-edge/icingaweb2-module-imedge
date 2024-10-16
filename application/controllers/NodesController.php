<?php

namespace Icinga\Module\Imedge\Controllers;

use gipfl\IcingaWeb2\CompatController;
use gipfl\IcingaWeb2\Link;
use gipfl\Web\Widget\Hint;
use Icinga\Module\Imedge\Config\Defaults;
use Icinga\Module\Imedge\Web\Table\NodesTable;
use IMEdge\Web\Rpc\IMEdgeClient;
use ipl\Html\Html;
use React\EventLoop\Loop;

use function Clue\React\Block\await;

class NodesController extends CompatController
{
    use DbTrait;
    use SpecialActions;

    public function indexAction()
    {
        $this->addInventoryTab();
        $this->addSingleTab($this->translate('Monitoring Nodes'));
        $this->addTitle($this->translate('Monitoring Edge Nodes'));
        $client = new IMEdgeClient();
        $nodeIdentifier = null;
        if ($client->socketIsWritable()) {
            try {
                $nodeIdentifier = await($client->request('node.getIdentifier'), Loop::get(), 2);
                $connectError = null;
            } catch (\Exception $e) {
                $connectError = sprintf(
                    'Connection to %s failed. Is the %s daemon running?',
                    Defaults::IMEDGE_SOCKET,
                    Defaults::APPLICATION_NAME
                );
            }
            $client = null;
        } else {
            $connectError = sprintf(
                'The local socket is not writable: %s. Is the %s daemon running?',
                Defaults::IMEDGE_SOCKET,
                Defaults::APPLICATION_NAME
            );
        }
        $refreshInterval = 7;
        $table = new NodesTable($this->db(), $nodeIdentifier);
        if ($table->count()) {
            $table->renderTo($this);
        } elseif (!$connectError) {
            $this->fallbackCheck();
        }

        if ($connectError) {
            $refreshInterval = 3;
            $this->content()->prepend(Hint::error($connectError));
        }

        $this->setAutorefreshInterval($refreshInterval);
    }

    protected function getNotWritableHint(): Hint
    {
        return Hint::error(sprintf(
            'The local %s socket is not writable: %s. Is the daemon running?',
            Defaults::APPLICATION_NAME,
            Defaults::IMEDGE_SOCKET
        ));
    }

    protected function fallbackCheck()
    {
        $client = new IMEdgeClient();
        if (!$client->socketIsWritable()) {
            $this->showLocalSocketNotWritable();
            return;
        }
        try {
            $identifier = await($client->request('node.getIdentifier'), Loop::get(), 2);
            $label = $identifier->name === $identifier->fqdn
                ? $identifier->name
                : sprintf($this->translate('%s (on %s)'), $identifier->name, $identifier->fqdn);

            // TODO: Move into table
            $this->content()->add(Hint::info(Html::sprintf(
                $this->translate('Local %s node (%s) is running, but has not been registered to our Inventory'),
                Defaults::APPLICATION_NAME,
                Link::create($label, 'inventory/datanode', [
                    'uuid' => $identifier->uuid,
                ], ['data-base-target' => '_next'])
            )));
        } catch (\Exception $e) {
            $this->content()->add(Hint::error(sprintf(
                $this->translate('Local %s node is failing: %s'),
                Defaults::APPLICATION_NAME,
                $e->getMessage()
            )));
        }
    }
}
