<?php

namespace Icinga\Module\Imedge\Controllers;

use Exception;
use gipfl\DataType\Settings;
use gipfl\IcingaWeb2\CompatController;
use gipfl\IcingaWeb2\Link;
use gipfl\IcingaWeb2\Url;
use gipfl\IcingaWeb2\Widget\Tabs;
use gipfl\Json\JsonString;
use gipfl\Web\Widget\Hint;
use Icinga\Module\Imedge\Web\Form\Node\RunRemoteMethodForm;
use Icinga\Module\Imedge\Web\Table\Node\NodeDbStreamTable;
use Icinga\Module\Imedge\Web\Table\Node\NodeInfoTable;
use IMEdge\Web\Rpc\IMEdgeClient;
use IMEdge\Web\Rpc\Inspection\MetaDataMethod;
use IMEdge\Web\Rpc\Inspection\NamespaceInfo;
use ipl\Html\Html;
use ipl\Html\Table;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use React\EventLoop\Loop;
use stdClass;

use function Clue\React\Block\await;
use function Clue\React\Block\awaitAll;

class NodeController extends CompatController
{
    use DbTrait;

    protected UuidInterface $uuid;
    protected IMEdgeClient $client;
    protected ?object $nodeInfo = null;

    public function init()
    {
        $this->uuid = Uuid::fromString($this->params->getRequired('uuid'));
        $this->client = (new IMEdgeClient())->withTarget($this->uuid->toString());
        try {
            $this->nodeInfo = $this->fetchNodeInfo();
        } catch (Exception $e) {
            $this->content()->add(Hint::error($e->getMessage()));
            return;
        }
    }

    protected function datanodeTabs(): Tabs
    {
        $urlParams = [
            'uuid' => $this->params->getRequired('uuid')
        ];
        return $this->tabs()->add('index', [
            'label'     => $this->translate('Node'),
            'url'       => 'imedge/node',
            'urlParams' => $urlParams,
        ])->add('rpc', [
            'label'     => $this->translate('RPC'),
            'url'       => 'imedge/node/rpc',
            'urlParams' => $urlParams,
        ])->add('dbStream', [
            'label'     => $this->translate('DB Stream'),
            'url'       => 'imedge/node/db-stream',
            'urlParams' => $urlParams,
        ]);
    }

    public function indexAction()
    {
        $this->setAutorefreshInterval(10000);
        if ($this->nodeInfo === null) {
            return;
        }
        $this->datanodeTabs()->activate('index');
        $settings = $this->nodeInfo->settings;
        $this->addTitle(Html::sprintf($this->translate('Monitoring Node: %s'),  $settings->getRequired('name')));
        $this->content()->add([
            Html::tag('h2', $this->translate('Node Settings')),
            new NodeInfoTable($this->nodeInfo->identifier, $settings),
        ]);

        $this->content()->add([
            Html::tag('h3', $this->translate('Registered features')),
            $this->prepareFeaturesTable($this->nodeInfo->features),
        ]);

        if (empty($this->nodeInfo->listeners)) {
            $this->content()->add(Hint::info($this->translate('Got no active TCP socket listener')));
        } else {
            $this->content()->add([
                Html::tag('h3', $this->translate('Active Listeners')),
                $this->prepareListenersTable((array) $this->nodeInfo->listeners),
            ]);
        }

        if (empty($this->nodeInfo->connections)) {
            $this->content()->add(Hint::info($this->translate('Got no active connections')));
        } else {
            $this->content()->add([
                Html::tag('h3', $this->translate('Connected nodes')),
                $this->prepareConnectionsTable((array) $this->nodeInfo->connections),
            ]);
        }
    }

    public function rpcAction()
    {
        $this->datanodeTabs()->activate('rpc');
        $settings = $this->nodeInfo->settings;
        $this->addTitle(Html::sprintf($this->translate('Remote Control: %s'),  $settings->getRequired('name')));
        $this->content()->add(new NamespaceInfo($this->nodeInfo->methods, Url::fromPath('imedge/node/method', [
            'uuid' => $this->uuid->toString()
        ])));
    }

    public function dbStreamAction()
    {
        $this->datanodeTabs()->activate('dbStream');
        if ($this->nodeInfo === null) {
            return;
        }
        $table = new NodeDbStreamTable($this->client, $this->enumDataNodes());
        $this->content()->add($table);
    }

    public function methodAction()
    {
        if ($this->nodeInfo === null) {
            return;
        }
        $this->datanodeTabs()->add('method', [
            'label' => $this->translate('RPC Method'),
            'url'   => $this->url(),
        ])->activate('method');
        $methodName = $this->params->getRequired('method');
        if (!isset($this->nodeInfo->methods->$methodName)) {
            $this->content()->add(Hint::error("Got no such method: $methodName"));
            return;
        }

        $this->addTitle($methodName . '()');
        $method = MetaDataMethod::fromSerialization($this->nodeInfo->methods->$methodName);
        $form = new RunRemoteMethodForm($methodName, $method);
        $form->on($form::ON_SUCCESS, function (RunRemoteMethodForm $form) use ($methodName, $method) {
            if ($method->type === 'request') {
                $result = await($this->client->request($methodName, $form->getNormalizedValues()), Loop::get(), 60);
                if (is_array($result) || is_object($result)) {
                    // Test:
                    // $result = count((array) $result);
                }
                if (is_string($result)) {
                    $this->content()->add(Html::tag('pre', $result));
                } else {
                    $this->content()->add(Html::tag('pre', JsonString::encode($result, JSON_PRETTY_PRINT)));
                }
            } else {
                $this->content()->add(Html::tag(print_r($method, 1)));
            }
        });
        $form->handleRequest($this->getServerRequest());
        $this->content()->add($form);
    }

    protected function prepareFeaturesTable(object $features): Table
    {
        $table = new Table();
        $table->addAttributes(['class' => ['common-table', 'table-row-selectable']]);
        $table->add(Table::row([
            $this->translate('Feature'),
            $this->translate('Path'),
        ], null, 'th'));
        foreach ((array) $features as $feature) {
            $table->add(Table::row([
                $feature->name,
                $feature->directory,
                // $feature->registered,
            ]));
        }

        return $table;
    }

    protected function prepareConnectionsTable(array $connections): Table
    {
        $table = new Table();
        $table->addAttributes(['class' => ['common-table', 'table-row-selectable']]);
        $table->add(Table::row([
            $this->translate('State'),
            $this->translate('UUID'),
            $this->translate('Peer'),
        ], null, 'th'));
        foreach ($connections as $connection) {
            $table->add(Table::row([
                $connection->state,
                $connection->peerIdentifier ?? null ? Link::create($connection->peerIdentifier, 'inventory/datanode', [
                    'uuid' => $connection->peerIdentifier
                ]) : '-',
                [
                    $connection->peerAddress,
                    ($connection->errorMessage ?? null) === null
                        ? null
                        : [
                        Html::tag('br'),
                        Html::tag('span', [
                            'class' => 'error'
                        ], $connection->errorMessage)
                    ]
                ]
            ]));
        }

        return $table;
    }

    protected function prepareListenersTable(array $listeners): Table
    {
        // TODO: Configured? Start/Stop/Add
        $table = new Table();
        $table->addAttributes(['class' => ['common-table', 'table-row-selectable']]);
        $table->add(Table::row([
            $this->translate('Socket'),
        ], null, 'th'));
        foreach ($listeners as $listener) {
            $table->add(Table::row([
                $listener,
            ]));
        }

        return $table;
    }

    protected function fetchNodeInfo(): stdClass
    {
        list($identifier, $methods, $features, $connections, $settings, $listeners) = awaitAll([
            $this->client->request('node.getIdentifier'),
            $this->client->request('node.getAvailableMethods'),
            $this->client->request('node.getFeatures'),
            $this->client->request('node.getConnections'),
            $this->client->request('node.getSettings'),
            $this->client->request('node.listListeners'),
        ], Loop::get());

        return (object) [
            'identifier'  => $identifier,
            'methods'     => $methods,
            'features'    => $features,
            'connections' => $connections,
            'listeners'   => $listeners,
            // config-type => IcingaMetrics/DataNode, config-version => v1, registered-metric-stores => []
            'settings'    => Settings::fromSerialization($settings),
        ];
    }

    protected function enumDataNodes(): array
    {
        $db = $this->db();
        $result = [];
        foreach ($db->fetchPairs($db->select()->from('datanode', ['uuid', 'label'])) as $uuid => $label) {
            $result[Uuid::fromBytes($uuid)->toString()] = $label;
        }

        return $result;
    }
}
