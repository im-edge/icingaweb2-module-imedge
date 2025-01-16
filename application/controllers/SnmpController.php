<?php

namespace Icinga\Module\Imedge\Controllers;

use gipfl\IcingaWeb2\CompatController;
use gipfl\IcingaWeb2\Link;
use gipfl\IcingaWeb2\Url;
use gipfl\IcingaWeb2\Widget\Tabs;
use gipfl\Web\Widget\Hint;
use Icinga\Module\Imedge\Config\Defaults;
use Icinga\Module\Imedge\Graphing\RrdImageLoader;
use Icinga\Module\Imedge\NodeControl\TargetShipper;
use Icinga\Module\Imedge\Web\Cards\SnmpInterfaceCards;
use Icinga\Module\Imedge\Web\Form\Filter\NodeFilterForm;
use Icinga\Module\Imedge\Web\Form\Inventory\SnmpCredentialForm;
use Icinga\Module\Imedge\Web\Form\Rpc\LiveSnmpScenarioForm;
use Icinga\Module\Imedge\Web\Form\Snmp\SnmpAgentForm;
use Icinga\Module\Imedge\Web\Table\Inventory\CredentialsTable;
use Icinga\Module\Imedge\Web\Table\Measurement\MeasurementsTable;
use Icinga\Module\Imedge\Web\Table\Snmp\SnmpDevicesTable;
use Icinga\Module\Imedge\Web\Table\Snmp\SnmpEntitiesTable;
use Icinga\Module\Imedge\Web\Table\Snmp\SnmpEntitySensorsTable;
use Icinga\Module\Imedge\Web\Table\Snmp\SnmpInterfacesTable;
use Icinga\Module\Imedge\Web\Table\Snmp\SnmpSysInfoDetailTable;
use Icinga\Module\Imedge\Web\Widget\Rpc\LiveSnmpResult;
use Icinga\Module\Imedge\Web\Widget\Snmp\InterfaceDetails;
use Icinga\Module\Imedge\Web\Widget\Snmp\ReachabilityHint;
use Icinga\Web\Notification;
use Icinga\Web\Session;
use IMEdge\Web\Data\Lookup\MacAddressBlockLookup;
use IMEdge\Web\Data\Model\Entity;
use IMEdge\Web\Data\Model\EntitySensor;
use IMEdge\Web\Data\Model\NetworkInterfaceConfig;
use IMEdge\Web\Data\Model\NetworkInterfaceStatus;
use IMEdge\Web\Data\Model\SnmpAgent;
use IMEdge\Web\Data\Model\SnmpSystemInfo;
use IMEdge\Web\Device\DeviceVendor;
use IMEdge\Web\Device\Widget\Entity\EntityTree;
use IMEdge\Web\Device\Widget\Entity\EntityTreeRenderer;
use IMEdge\Web\Grapher\GraphRendering\ImedgeGraph;
use IMEdge\Web\Grapher\GraphRendering\TimeControl;
use IMEdge\Web\Rpc\IMEdgeClient;
use ipl\Html\Html;
use ipl\Html\HtmlDocument;
use Ramsey\Uuid\Uuid;
use React\EventLoop\Loop;

use function Clue\React\Block\await;

class SnmpController extends CompatController
{
    use DbTrait;
    use SpecialActions;
    use WebClientInfo;

    protected const GOT_PREFERRED_URL_PARAM = 'gotPreferredUrl';

    protected SnmpSystemInfo $sysInfo;
    protected SnmpAgent $agent;

    public function init()
    {
        // Hint: we intentionally do not call our parent's init() method
    }
    /**
     * @api
     */
    public function devicesAction(): void
    {
        $this->setAutorefreshInterval(6);
        $this->addInventoryTab();
        $this->addSingleTab($this->translate('Devices'));
        $this->addTitle($this->translate('SNMP Devices'));
        $this->actions()->add([
            Link::create($this->translate('Add'), 'imedge/snmp/device', null, [
                'class' => 'icon-plus',
                'data-base-target' => '_main',
            ]),
            Link::create($this->translate('Export'), 'imedge/data/export', null, [
                'class' => 'icon-download',
                'target' => '_blank',
            ]),
        ]);
        $table = new SnmpDevicesTable($this->db());
        $form = new NodeFilterForm($this->db());
        $form->setAction((string) $this->getOriginalUrl()->without('datanode'));
        $form->handleRequest($this->getServerRequest());
        $this->content()->add($form);
        if ($uuid = $form->getUuid()) {
            $table->getQuery()->where('si.datanode_uuid = ?', $uuid->getBytes());
        }

        $table->renderTo($this);
    }

    public function preferredDeviceViewAction(): void
    {
        $this->redirectNow($this->getPreferredDeviceUrl()->with('uuid', $this->params->get('uuid')));
    }

    /**
     * @api
     */
    public function deviceAction(): void
    {
        if ($this->params->get('uuid') === null || $this->params->get('action') === 'modify') {
            if ($this->params->get('uuid')) {
                $info = $this->getDevice();
                $agent = $this->requireAgent();
                if ($info) {
                    $this->deviceTabs($info)->activate('device');
                } else {
                    $this->agentTabs($agent)->activate('device');
                }
                $form = new SnmpAgentForm($this->dbStore(), Uuid::fromString($this->params->get('uuid')));
                $agent = $this->requireAgent();
                $this->addDeviceHeader($agent, $this->getDevice(), $this->translate('Modify SNMP Device'));
                $this->linkBack($this->url()->without('action'));
            } else {
                $this->addInventoryTab();
                $this->tabs()->add('devices', [
                    'label' => $this->translate('Devices'),
                    'url'   => 'imedge/snmp/devices',
                ]);
                $form = new SnmpAgentForm($this->dbStore());
                $this->addSingleTab($this->translate('New SNMP Device'));
                $this->addTitle($this->translate('Add a new SNMP device'));
                $this->linkBack('imedge/snmp/devices');
            }
            $form->on($form::ON_SUCCESS, function (SnmpAgentForm $form) {
                try {
                    $shipper = new TargetShipper($this->db());
                    $nodeUuid = $form->getDatanodeUuid();
                    $deviceUuid = $form->getUuid();
                    $shipper->shipCredentials($nodeUuid);
                    $shipper->shipTargets($nodeUuid);
                    $client = (new IMEdgeClient())->withTarget($nodeUuid->toString());
                    await($client->request('snmp.triggerScenario', [
                        'deviceUuid' => $deviceUuid,
                        'name' => 'sysInfo',
                    ]), Loop::get());
                    await($client->request('snmp.triggerScenario', [
                        'deviceUuid' => $deviceUuid,
                        'name' => 'interfaceConfig',
                        'delay' => 5,
                    ]), Loop::get());
                    await($client->request('snmp.triggerScenario', [
                        'deviceUuid' => $deviceUuid,
                        'name' => 'interfaceStatus',
                        'delay' => 6,
                    ]), Loop::get());
                    Notification::success('Device has been submitted');
                    $this->redirectNow('imedge/snmp/devices#!imedge/snmp/device?uuid=' . $deviceUuid->toString());
                } catch (\Exception $exception) {
                    Notification::error($exception->getMessage());
                }
            });
            $form->handleRequest($this->getServerRequest());
            $this->content()->add($form);
            return;
        } else {
            $this->actions()->add(Link::create(
                $this->translate('Modify'),
                $this->url()->with('action', 'modify'),
                null,
                ['class' => 'icon-edit']
            ));
        }
        $info = $this->getDevice();
        $agent = $this->requireAgent();
        if ($info) {
            $this->deviceTabs($info)->activate('device');
        } else {
            $this->agentTabs($agent)->activate('device');
        }
        $this->addDeviceHeader($agent, $info, $this->translate('SNMP Device'));
        $reachability = new ReachabilityHint($agent, $this->dbStore());
        $this->setAutorefreshInterval($reachability->hasBeenChecked() ? 15 : 2);
        $this->content()->add($reachability);
        if ($info) {
            $this->content()->add(new SnmpSysInfoDetailTable($info));
        }
        $this->rememberPreferredDeviceUrl();
    }

    /**
     * @api
     */
    public function snmpInterfacesAction(): void
    {
        $this->rememberPreferredDeviceUrl();
        $device = $this->requireDevice();
        $agent = $this->requireAgent();
        // $search = hex2bin(str_replace(['-', ':'], '', $search));
        $this->deviceTabs($device)->activate('interfaces');
        $this->addDeviceHeader($agent, $device, $this->translate('Interfaces'));

        $this->actions()->add('View: ');
        $renderer = $this->toggle($this->actions(), 'view', [
            'table' => $this->translate('Table'),
            'cards' => $this->translate('Cards'),
        ]);
        $this->actions()->add(new TimeControl($this->url()));

        if ($renderer === 'table') {
            $table = new SnmpInterfacesTable(
                $this->db(),
                $device->getUuid(),
                $this->params->get('start', 'end-25hour')
            );
            $this->actions()->add('Admin State: ');
            $adminState = $this->toggle($this->actions(), 'adminState', [
                'up' => $this->translate('All States'),
                'all' => $this->translate('Only UP'),
            ]);
            $this->actions()->add('Operational: ');
            $operState = $this->toggle($this->actions(), 'operState', [
                'up' => $this->translate('All States'),
                'all' => $this->translate('Only UP'),
            ]);
            if ($adminState === 'up') {
                $table->filterAdminUp();
            }
            if ($operState === 'up') {
                $table->filterOperUp();
            }
            if ($table->count() === 0) {
                $this->content()->add(
                    Hint::info($this->translate('Got no interfaces from this device'))
                );
            } else {
                $table->renderTo($this);
            }
            return;
        }
        $this->actions()->add('Metrics: ');
        $template = $this->toggle($this->actions(), 'template', [
            'if_traffic' => $this->translate('Packets'),
            'if_packets' => $this->translate('Traffic'),
        ]);
        $this->actions()->add('Admin State: ');
        $adminState = $this->toggle($this->actions(), 'adminState', [
            'up' => $this->translate('All States'),
            'all' => $this->translate('Only UP'),
        ]);
        $this->actions()->add('Operational: ');
        $operState = $this->toggle($this->actions(), 'operState', [
            'up' => $this->translate('All States'),
            'all' => $this->translate('Only UP'),
        ]);
        $cards = new SnmpInterfaceCards(
            $this->db(),
            $device->getUuid(),
            $template,
            $this->params->get('start', 'end-4hour')
        );
        if ($adminState === 'up') {
            $cards->filterAdminUp();
        }
        if ($operState === 'up') {
            $cards->filterOperUp();
        }
        $this->content()->add($cards);
    }

    /**
     * @api
     */
    public function snmpEntitiesAction(): void
    {
        $device = $this->requireDevice();
        $this->deviceTabs($device)->activate('entities');
        $this->addDeviceHeader($this->requireAgent(), $device, $this->translate('Entities'));
        $table = new SnmpEntitiesTable($this->db());
        $table->filterSystemUuid($device->getUuid());
        $db = $this->db();

        $entities = [];
        $query = $db->select()->from(Entity::TABLE)->where('device_uuid = ?', $device->getUuid()->getBytes())
            ->order('relative_position')
            ->order('entity_index')
        ;
        foreach ($db->fetchAll($query) as $row) {
            $entities[$row->entity_index] = Entity::create((array) $row)->setStored();
        }
        $tree = EntityTree::create($entities);

        $entitySensors = [];
        $query = $db->select()->from(EntitySensor::TABLE)->where('device_uuid = ?', $device->getUuid()->getBytes());
        foreach ($db->fetchAll($query) as $row) {
            $entitySensors[$row->entity_index] = EntitySensor::create((array) $row)->setStored();
        }

        $interfaceConfigs = [];
        $query = $db->select()
            ->from(['ic' => NetworkInterfaceConfig::TABLE], 'ic.*')
            ->join(
                ['ei' => 'inventory_entity_ifmap'],
                'ei.device_uuid = ic.system_uuid AND ic.if_index = ei.if_index',
                ['entity_index']
            )
            ->where('ei.device_uuid = ?', $device->getUuid()->getBytes())
            ->where('ei.if_index != 0'); // TODO: re-check alias mapping on poll
        foreach ($db->fetchAll($query) as $row) {
            $entityId = $row->entity_index;
            unset($row->entity_index);
            $interfaceConfigs[$entityId] ??= [];
            $interfaceConfigs[$entityId][$row->if_index] = NetworkInterfaceConfig::create((array) $row)->setStored();
        }

        $interfaceStatuses = [];
        $query = $db->select()
            ->from(['nis' => NetworkInterfaceStatus::TABLE], 'nis.*')
            ->join(
                ['ei' => 'inventory_entity_ifmap'],
                'ei.device_uuid = nis.system_uuid AND nis.if_index = ei.if_index',
                ['entity_index']
            )
            ->where('ei.device_uuid = ?', $device->getUuid()->getBytes())
            ->where('ei.if_index != 0'); // TODO: re-check alias mapping on poll
        foreach ($db->fetchAll($query) as $row) {
            $entityId = $row->entity_index;
            unset($row->entity_index);
            $interfaceStatuses[$entityId] ??= [];
            $interfaceStatuses[$entityId][$row->if_index] = NetworkInterfaceStatus::create((array) $row)->setStored();
        }

        $tree->setSensors($entitySensors);
        $tree->setInterfaces($interfaceConfigs, $interfaceStatuses);
        $this->content()->add(new EntityTreeRenderer($tree, new RrdImageLoader($db)));

        if ($table->count() === 0) {
            $this->content()->add(
                Hint::info($this->translate('Got no entities from this device'))
            );
        } else {
            $table->renderTo($this);
        }
        $this->rememberPreferredDeviceUrl();
    }

    /**
     * @api
     */
    public function snmpSensorsAction(): void
    {
        $device = $this->requireDevice();
        $agent = $this->requireAgent();
        $this->deviceTabs($device)->activate('sensors');
        $this->addDeviceHeader($agent, $device, $this->translate('Sensors'));
        $table = new SnmpEntitySensorsTable($this->db(), $device->getUuid());
        $table->renderTo($this);
        $this->rememberPreferredDeviceUrl();
    }

    public function measurementsAction()
    {
        $agent = $this->requireAgent();
        $device = $this->requireDevice();
        $this->deviceTabs($device)->activate('measurements');
        $this->addDeviceHeader($agent, $device, $this->translate('Measurements'));
        $table = new MeasurementsTable($this->db());
        $table->filterDevice($device->getUuid());
        $table->renderTo($this);
        $this->rememberPreferredDeviceUrl();
    }

    /**
     * @api
     */
    public function liveAction(): void
    {
        $agent = $this->requireAgent();
        $device = $this->getDevice();
        if ($device) {
            $this->deviceTabs($device)->activate('live');
        } else {
            $this->agentTabs($agent)->activate('live');
        }
        $this->addDeviceHeader($agent, $device, $this->translate('Live SNMP Queries'));

        $form = new LiveSnmpScenarioForm();
        $form->handleRequest($this->getServerRequest());
        $this->content()->add($form);
        $scenarioName = $form->getValue('scenarioName');
        $this->rememberPreferredDeviceUrl();
        if ($scenarioName === null) {
            return;
        }
        $client = (new IMEdgeClient())->withTarget(Uuid::fromBytes($agent->get('datanode_uuid'))->toString());
        $wantsScenarioObject = $form->getValue('resultType') === 'object';
        $request = $wantsScenarioObject ? 'snmp.scenarioObject' : 'snmp.scenario';

        try {
            $result = await($client->request($request, [
                'credentialUuid' => Uuid::fromBytes($agent->get('credential_uuid')),
                'address' => ['ip' => inet_ntop($agent->get('ip_address')), 'port' => $agent->get('snmp_port')],
                'name' => $scenarioName,
                'deviceUuid' => Uuid::fromBytes($agent->get('agent_uuid')),
            ]), Loop::get());

            $this->content()->add(
                $wantsScenarioObject
                ? Html::tag('pre', print_r($result, 1))
                : new LiveSnmpResult($scenarioName, $result, $this->db())
            );
        } catch (\Exception $e) {
            if (str_starts_with($e->getMessage(), 'Unable to connect to unix domain socket')) {
                $message = 'IMEdge daemon is not reachable';
            } else {
                $message = $e->getMessage();
            }
            $this->content()->add(Html::tag('br')); // Inline form has no margin
            $this->content()->add(Hint::error($message));
        }
    }

    /**
     * @api
     */
    public function snmpInterfaceAction(): void
    {
        $this->content()->addAttributes([
            'class' => 'imedge-graph-set'
        ]);
        $device = $this->requireDevice('device_uuid');
        $ifIndex = $this->params->getRequired('if_index');
        $dbIdx = [
            'system_uuid' => $device->getUuid()->getBytes(),
            'if_index' => $ifIndex
        ];
        $macLookup = new MacAddressBlockLookup($this->db());
        //$ifConfig = $this->dbStore()->load($dbIdx, NetworkInterfaceConfig::class);
        $ifConfig = NetworkInterfaceConfig::load($this->dbStore(), $dbIdx);
        $ifStatus = NetworkInterfaceStatus::load($this->dbStore(), $dbIdx);

        $this->addSingleTab('Interface');
        $this->addTitle(
            $ifConfig->get('if_name') . ($ifConfig->get('if_alias') ? ': ' . $ifConfig->get('if_alias') : '')
        );
        $this->controls()->add([
            $ifConfig->get('if_description') ? $ifConfig->get('if_description') . ', ' : '',
            $ifConfig->get('status_admin') . '/' . $ifStatus->get('status_operational'),
        ]);

        $imgLoader = new RrdImageLoader($this->db());
        $definitions = [
            'if_traffic' => ['if_traffic', $this->translate('Traffic, bits/s'), '32em'],
            'if_packets' => ['if_packets', $this->translate('Packets/s'), '16em'],
            'if_error'   => ['if_error', $this->translate('Errors/s'), '16em'],
        ];
        foreach ($definitions as $measurementName => [$template, $title, $height]) {
            $img = $imgLoader->getDeviceImg($device->getUuid(), $measurementName, $ifIndex, $template);
            if ($img) {
                // $img->loadImmediately();
                $img->graph->layout->setDarkMode($this->wantsDarkMode());
                $this->content()->add([
                    (new ImedgeGraph($img, $this->url(), $title))->setAttribute('style', "height: $height"),
                    Link::create('Details', 'imedge/measurement/file', [
                        'uuid' => key($img->fileInfos)
                    ])
                ]);
            }
        }
        $this->content()->add(new InterfaceDetails($this->controls(), $macLookup, $ifConfig, $ifStatus));
    }

    /**
     * @api
     */
    public function credentialsAction(): void
    {
        $this->addInventoryTab();
        $this->addSingleTab($this->translate('Credentials'));
        $this->addTitle($this->translate('SNMP credentials'));
        $this->actions()->add(
            Link::create($this->translate('Add'), 'imedge/snmp/credential', null, [
                'class' => 'icon-plus',
                'data-base-target' => '_main'
            ])
        );
        (new CredentialsTable($this->db()))->renderTo($this);
    }

    /**
     * @api
     */
    public function credentialAction(): void
    {
        $uuid = $this->params->get('uuid');
        if ($uuid !== null && strlen($uuid)) {
            $uuid = Uuid::fromString($this->params->get('uuid'));
            $this->addSingleTab('Modify');
            $this->addTitle($this->translate('Modify SNMP credential'));
        } else {
            $uuid = null;
            $this->addInventoryTab();
            $this->tabs()->add('devices', [
                'label' => $this->translate('Credentials'),
                'url'   => 'imedge/snmp/credentials',
            ]);
            $this->addSingleTab('New SNMP credential');
            $this->linkBack('imedge/snmp/devices');
            $this->addTitle($this->translate('Add a new SNMP credential'));
        }

        $form = (new SnmpCredentialForm($this->dbStore(), $uuid))
            ->on(SnmpCredentialForm::ON_SUCCESS, function (SnmpCredentialForm $form) {
                $this->redirectNow(
                    'imedge/snmp/credentials#!imedge/snmp/credential?uuid=' . $form->getUuid()->toString()
                );
            });
        $this->content()->add($form->handleRequest($this->getServerRequest()));
        if ($form->hasBeenDeleted()) {
            $this->redirectNow('imedge/snmp/credentials#!__CLOSE__');
        }
    }

    protected function addDeviceHeader(SnmpAgent $agent, ?SnmpSystemInfo $sysInfo, $title)
    {
        $label = $agent->get('label')
            ?? ($sysInfo ? $sysInfo->get('system_name') : null)
            ?? inet_ntop($agent->get('ip_address'));
        if ($label === '') {
            $label = inet_ntop($agent->get('ip_address'));
        }
        $this->addTitle($label . ': ' . $title);
        if ($sysInfo) {
            $this->controls()->prepend(DeviceVendor::getVendorLogo($sysInfo));
        }
    }

    protected function agentTabs(SnmpAgent $agent): Tabs
    {
        $uuid = Uuid::fromBytes($agent->get('agent_uuid'))->toString();
        return $this->tabs()->add('device', [
            'label' => $this->translate('Device'),
            'url'   => 'imedge/snmp/device',
            'urlParams' => ['uuid' => $uuid]
        ])->add('live', [
            'label' => $this->translate('Live'),
            'url'   => 'imedge/snmp/live',
            'urlParams' => ['uuid' => $uuid]
        ]);
    }

    protected function deviceTabs(SnmpSystemInfo $info): Tabs
    {
        $uuid = Uuid::fromBytes($info->get('uuid'))->toString();
        return $this->tabs()->add('device', [
            'label' => $this->translate('Device'),
            'url'   => 'imedge/snmp/device',
            'urlParams' => ['uuid' => $uuid]
        ])->add('interfaces', [
            'label' => $this->translate('Interfaces'),
            'url'   => 'imedge/snmp/snmp-interfaces',
            'urlParams' => ['uuid' => $uuid]
        ])->add('entities', [
            'label' => $this->translate('Entities'),
            'url'   => 'imedge/snmp/snmp-entities',
            'urlParams' => ['uuid' => $uuid]
        ])->add('sensors', [
            'label' => $this->translate('Sensors'),
            'url'   => 'imedge/snmp/snmp-sensors',
            'urlParams' => ['uuid' => $uuid]
        ])->add('measurements', [ // TODO: device/entity/subject metrics?
            'label' => $this->translate('Measurements'),
            'url'   => 'imedge/snmp/measurements',
            'urlParams' => ['uuid' => $uuid]
        ])->add('live', [
            'label' => $this->translate('Live'),
            'url'   => 'imedge/snmp/live',
            'urlParams' => ['uuid' => $uuid]
        ]);
    }

    protected function getDevice(string $param = 'uuid'): ?SnmpSystemInfo
    {
        try {
            return $this->sysInfo ??= SnmpSystemInfo::load(
                $this->dbStore(),
                Uuid::fromString($this->params->getRequired($param))->getBytes()
            );
        } catch (\Exception $e) {
            return null;
        }
    }

    protected function requireDevice(string $param = 'uuid'): SnmpSystemInfo
    {
        return $this->sysInfo ??= SnmpSystemInfo::load(
            $this->dbStore(),
            Uuid::fromString($this->params->getRequired($param))->getBytes()
        );
    }

    protected function toggle(HtmlDocument $parent, string $paramName, array $options): string
    {
        $default = array_keys($options)[0];
        $other = array_keys($options)[1];
        $value = $this->params->get($paramName, $default);
        $url = $this->url()->without(self::GOT_PREFERRED_URL_PARAM);
        if ($value === $default) {
            $parent->add(Link::create($options[$other], $url->with($paramName, $other)));
            return $default;
        } else {
            $parent->add(Link::create($options[$default], $url->with($paramName, $default)));
            return $other;
        }
    }

    protected function rememberPreferredDeviceUrl(): void
    {
        $url = $this->url();
        if ($url->getParam(self::GOT_PREFERRED_URL_PARAM)) {
            return;
        }
        $url = $url->without(['page', 'limit', 'uuid', self::GOT_PREFERRED_URL_PARAM]);
        $session = Session::getSession()->getNamespace(Defaults::MODULE_NAME);
        $windowSession = $this->Window()->getSessionNamespace(Defaults::MODULE_NAME);
        $session->set('preferred-device-url', $url->getRelativeUrl());
        $windowSession->set('preferred-device-url', $url->getRelativeUrl());
    }

    protected function getPreferredDeviceUrl(): Url
    {
        $windowSession = $this->Window()->getSessionNamespace(Defaults::MODULE_NAME);
        $url = $windowSession->get('preferred-device-url');
        if ($url === null) {
            $session = Session::getSession()->getNamespace(Defaults::MODULE_NAME);
            $url = $session->get('preferred-device-url');
        }

        $allowedForNewDevice = [
            'imedge/snmp/device',
            'imedge/snmp/live',
        ];
        /*
        $allowedForNewDevice = [];
        // TODO: Urls -> imedge/snmp-device/whatever, imedge/credential & prepare tabs in init
        foreach ($this->tabs() as $tab) {
            $allowedForNewDevice[] = $tab->getUrl()->without('uuid')->getRelativeUrl();
        }
        */

        if ($url !== null) {
            if ($this->getDevice() || in_array($url, $allowedForNewDevice)) {
                return Url::fromPath($url)->with(self::GOT_PREFERRED_URL_PARAM, true);
            }

            return Url::fromPath('imedge/snmp/device')->with(self::GOT_PREFERRED_URL_PARAM, true);
        }

        return Url::fromPath('imedge/snmp/device');
    }

    protected function requireAgent(string $param = 'uuid'): SnmpAgent
    {
        return $this->agent ??= SnmpAgent::load(
            $this->dbStore(),
            Uuid::fromString($this->params->getRequired($param))->getBytes()
        );
    }
}
