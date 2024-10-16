<?php

namespace Icinga\Module\Imedge\Web\Table\Snmp;

use gipfl\IcingaWeb2\Icon;
use gipfl\IcingaWeb2\Link;
use gipfl\IcingaWeb2\Table\ZfQueryBasedTable;
use gipfl\ZfDb\Adapter\Adapter;
use Icinga\Module\Imedge\Graphing\RrdImageLoader;
use Icinga\Module\Imedge\Web\Enrichment\NetworkPortInfo;
use Icinga\Util\Format;
use IMEdge\Svg\SvgUtils;
use IMEdge\Web\Data\ForeignModel\IanaIfType;
use IMEdge\Web\Data\Lookup\MacAddressBlockLookup;
use IMEdge\Web\Data\Widget\MacAddress;
use IMEdge\Web\Device\Widget\Pluggable\RegisteredJack6P2C;
use IMEdge\Web\Device\Widget\Port\EthernetPort;
use IMEdge\Web\Grapher\GraphRendering\ImedgeGraphPreview;
use IMEdge\Web\Grapher\GraphRendering\RrdImage;
use ipl\Html\Html;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

class SnmpInterfacesTable extends ZfQueryBasedTable
{
    protected $searchColumns = [
        'i.if_name',
        'i.if_alias'
    ];

    protected $defaultAttributes = [
        'class' => ['interfaces-table', 'common-table', 'table-row-selectable'],
        'data-base-target' => '_next',
    ];

    protected UuidInterface $systemUuid;
    protected MacAddressBlockLookup $macLookup;
    protected RrdImageLoader $imgLoader;
    protected string $start;

    public function __construct(Adapter $db, UuidInterface $systemUuid, string $start)
    {
        parent::__construct($db);
        $this->systemUuid = $systemUuid;
        $this->macLookup = new MacAddressBlockLookup($db);
        $this->imgLoader = new RrdImageLoader($db);
        $this->start = $start;
    }

    protected function assemble()
    {
        $this->getAttributes()->add([
            'class' => ['limited-row-height-table', 'interfaces-table'],
            'style' => 'width: 100%; max-width: unset'
        ]);
    }

    public function renderRow($row)
    {
        if (IanaIfType::isEthernet((int) $row->if_type)) {
            $port = new EthernetPort();
            $width = $port->getFullWidth();
            $height = $port->getFullHeight();
            $svg = SvgUtils::createSvg2([
                // 'width' => $width * 1.5,
                // 'height' => $height * 1.5,
                'height' => '2em',
            ]);
            $svg->addAttributes([
                'viewBox' => "0 0 $width $height",
            ]);
            $svg->add($port);
            NetworkPortInfo::applyTo($port, $row);
        } elseif (IanaIfType::describe($row->if_type) === 'adsl') {
            $port = new RegisteredJack6P2C();
            // $width = $port->getFullWidth();
            // $height = $port->getFullHeight();
            $width  = 9.65;
            $height = 7.60;
            $svg =  SvgUtils::createSvg2([
                // 'width' => $width * 1.5,
                // 'height' => $height * 1.5,
                'height' => '2em',
            ]);
            $svg->addAttributes([
                'viewBox' => "0 0 $width $height",
            ]);
            $svg->add($port);
        } else {
            $port = null;
            $svg = null;
        }

        $rowClass = 'state';
        switch ($row->status_admin) {
            case 'up':
                $state = Icon::create('ok', [
                    'class' => 'up',
                    'title' => $this->translate('Administratively up')
                ]);
                $stateColor = 'state-ok';
                if ($row->status_oper === 'down') {
                    $state->addAttributes([
                        'style' => 'color: red'
                    ]);
                    if ($port) {
                        //$port->setState('critical');
                    }
                    $stateColor = 'state-critical';

                }
                if ($row->status_oper === 'up') {
                    if ($port) {
                        // $port->setState('ok');
                    }
                    $state->addAttributes([
                        'style' => 'color: green'
                    ]);
                }
                break;
            case 'down':
                $rowClass .= ' state-critical';
                $state = Icon::create('cancel', [
                    'title' => $this->translate('Administratively down')
                ]);
                $stateColor = 'state-critical-handled';
                break;
            case 'testing':
                $state = Icon::create('eye', [
                    'class' => 'state-icon state-warning',
                    'title' => $this->translate('Interface is state "Testing"')
                ]);
                $stateColor = 'state-warning';
                break;
            default:
                $stateColor = 'state-pending';
        }

        $img = $this->getTrafficImage($row->if_index);
        return static::row([
            // $row->if_index,
            // $row->connector_present,  // TODO: re-read
            // $row->status_duplex,
            [
                $svg ? Html::tag('div', ['style' => 'float: left; display: inline-block; margin-right: 0.5em'], $svg) : [$this->describeIfType($row->if_type, $stateColor), Html::tag('br')],
                Link::create($row->if_name, 'imedge/snmp/snmp-interface', [
                    'device_uuid' => Uuid::fromBytes($row->system_uuid)->toString(),
                    'if_index'    => $row->if_index,
                    'start'       => $this->start,
                ]),
                ($row->if_alias && $row->if_alias !== $row->if_name) ? ' (' . $row->if_alias . ')' : null,
                [
                    ($row->entity_description ? [Html::tag('br'), $row->entity_description] :  null),
                    ($row->physical_address ? [Html::tag('br'), MacAddress::fromBinary($row->physical_address, $this->macLookup)] :  null),
                    Html::tag('br'),
                    implode(', ', array_filter([
                        'MTU ' . $row->mtu,
                        ($row->speed_kbit > 0
                            ? Format::bits($row->speed_kbit * 1000) . '/s'
                            : null),
                        sprintf('%s / %s', $row->status_admin, $row->status_oper)
                    ], function ($v) {
                        return $v !== null;
                    })),
                    $img ? [Html::tag('br'), $img] : null,
                ]
            ],
        ], [
            'class' => $rowClass
        ]);
    }

    protected function getTrafficImage($ifIndex): ?ImedgeGraphPreview
    {
        $loader = $this->imgLoader;
        $instance = $ifIndex;
        $systemUuid = $this->systemUuid;
        $imgTraffic = $loader->getDeviceImg($systemUuid, 'if_traffic', $instance, 'if_traffic_simple');
        // $imgPackets = $loader->getDeviceImg($systemUuid, 'if_packets', $instance, 'if_packets');
        // $imgErrors = $loader->getDeviceImg($systemUuid, 'if_error', $instance, 'if_error');
        if (! $imgTraffic) {
            return null;
        }
        $container = new ImedgeGraphPreview($imgTraffic);
        $container->addAttributes(['style' => 'width: 36em; height: 10em;']);
        foreach ([$imgTraffic/*, $imgErrors, $imgPackets*/] as $img) {
            /** @var RrdImage $img */
            if ($img) {
                $img->graph->timeRange->setStart($this->start);
                // $img->graph->layout->setOnlyGraph();
                $img->graph->layout->disableXAxis();
            }
        }

        return $container;
    }

    protected function describeIfType($type, $stateColor)
    {
        if (isset(IanaIfType::TYPES[$type])) {
            $iconClass = ['class' => "state-icon $stateColor"];
            switch (IanaIfType::TYPES[$type]) {
                case 'other':
                    return Icon::create('help', $iconClass + ['title' => $this->translate('Other')]);
                case 'ieee80216WMAN':
                    return Icon::create('wifi', $iconClass + ['title' => $this->translate('WiMAX')]);
                case 'softwareLoopback':
                    return Icon::create('arrows-cw', $iconClass + ['title' => $this->translate('Software Loopback')]);
                default:
                    return [$state ?? '', \sprintf('%s ', IanaIfType::TYPES[$type])];
            }
        } else {
            return $type;
        }
    }

    public function prepareQuery()
    {
        // TODO: if no agent -> join all of them, add columns
        return $this->db()->select()
            ->from(['i' => 'snmp_interface_config'], [
                'id'                     => 'i.if_index', // wrong, only for NetworkPortInfo
                'if_status_admin'           => 'i.status_admin',
                'if_status_oper'            => 's.status_operational',
                'if_status_duplex'          => 's.status_duplex',
                'if_status_stp'          => '(NULL)',
                'if_speed_kbit'             => 'i.speed_kbit',
                'if_usage_out'             => '(null)',
                'if_usage_in'             => '(null)',
                'relative_position'             => 'pe.relative_position',
                // NetworkPortInfo End

                'if_index'               => 'i.if_index',
                'if_type'                => 'i.if_type',
                'if_name'                => 'i.if_name',
                'if_alias'               => 'i.if_alias',
                'oui'                    => '(NULL)',
                'physical_address' => 'i.physical_address',
                'mtu'                    => 'i.mtu',
                'speed_kbit'             => 'i.speed_kbit',
                'status_admin'           => 'i.status_admin',
                'status_oper'            => 's.status_operational',
                'status_duplex'          => 's.status_duplex',
                'connector_present'      => 's.connector_present',
                'entity_description'     => 'pe.description',
                'entity_model'           => 'pe.model_name',
                'system_uuid'            => 'i.system_uuid',
            ])
            ->joinLeft(
                ['s' => 'snmp_interface_status'],
                's.system_uuid = i.system_uuid AND s.if_index = i.if_index',
                []
            )
            ->joinLeft(
                ['ei' => 'inventory_entity_ifmap'],
                'ei.device_uuid = i.system_uuid AND ei.if_index = i.if_index',
                []
            )->joinLeft(
                ['pe' => 'inventory_physical_entity'],
                'pe.device_uuid = ei.device_uuid AND pe.entity_index = ei.entity_index',
                []
            )
            ->where('i.system_uuid = ?', $this->systemUuid->getBytes())
            ->where('i.status_admin != ?', 'down')
            ->limit(14)
            ->order('if_index');
    }
}
