<?php

namespace Icinga\Module\Imedge\Controllers;

use gipfl\IcingaWeb2\CompatController;
use gipfl\IcingaWeb2\Img;
use gipfl\IcingaWeb2\Link;
use gipfl\Web\Widget\Hint;
use Icinga\Module\Imedge\Config\Defaults;
use Icinga\Module\Imedge\Web\Table\Node\NodesTable;
use IMEdge\Web\Rpc\IMEdgeClient;
use ipl\Html\Html;
use React\EventLoop\Loop;

use function Clue\React\Block\await;

class IndexController extends CompatController
{
    use DbTrait;

    protected function getMainDescription()
    {
        return [
            Html::tag('p', Html::sprintf($this->translate('%s ships a bunch of powerful components for your Open Source Monitoring
 environment. As in Edge Computing best practices, this brings processing
 closer to the data source.'), Html::tag('strong', Defaults::APPLICATION_NAME))),
            Html::tag('p', Html::sprintf(
                $this->translate('Currently, this module allows to control local and remote %s nodes,
 is proxying graph requests, and provides access to our %s database.'),
                Html::tag('strong', Defaults::APPLICATION_NAME),
                Link::create($this->translate('Inventory'), 'imedge/inventory')
            ))
        ];
    }
    public function indexAction()
    {
        $this->addSingleTab(Defaults::APPLICATION_NAME);
        $this->addTitle(sprintf($this->translate('Welcome to %s!'), Defaults::APPLICATION_NAME));
        $isAdmin = $this->Auth()->hasPermission('imedge/admin');
        $this->content()->add(Html::tag('div', [
            'class' => 'text-content',
            'data-base-target' => '_main',
        ], [
            Img::create('img/imedge/imedge-logo.svg', null, [
                'class' => 'logo-main'
            ]),
            $this->getMainDescription(),
        ]));


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

        if ($connectError) {
            $this->content()->prepend(Hint::error($connectError));
        }


        if (! $this->daemonIsRunning()) {
            $this->content()->add([
                Html::tag('h2', $this->translate('Configuration')),
                Html::tag('p', Html::sprintf(
                    $this->translate('This module needs a %s configuration.'),
                    $isAdmin ? Link::create(
                        $this->translate('database connection'),
                        'imedge/configuration/database'
                    ) : $this->translate('database connection')
                )),
            ]);
            return;
        } elseif (! $this->hasDbConfiguration()) {
            $this->content()->add([
                Html::tag('h2', $this->translate('Configuration')),
                Html::tag('p', Html::sprintf(
                    $this->translate('This module needs a %s configuration.'),
                    $isAdmin ? Link::create(
                        $this->translate('database connection'),
                        'imedge/configuration/database'
                    ) : $this->translate('database connection')
                )),
            ]);
            return;
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
                Link::create($label, 'imedge/node', [
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

    protected function daemonIsRunning(): bool
    {
        return true;
    }

    protected function hasDbConfiguration(): bool
    {
        return true;
    }
}
