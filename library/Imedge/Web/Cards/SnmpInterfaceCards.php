<?php

namespace Icinga\Module\Imedge\Web\Cards;

use gipfl\IcingaWeb2\Icon;
use gipfl\IcingaWeb2\Link;
use gipfl\IcingaWeb2\Url;
use gipfl\Translation\TranslationHelper;
use gipfl\ZfDb\Adapter\Adapter;
use Icinga\Module\Imedge\Graphing\RrdImageLoader;
use Icinga\Module\Imedge\Web\Enrichment\NetworkPortInfo;
use Icinga\Util\Format;
use IMEdge\Svg\SvgUtils;
use IMEdge\Web\Data\ForeignModel\IanaIfType;
use IMEdge\Web\Data\Lookup\MacAddressBlockLookup;
use IMEdge\Web\Data\Widget\MacAddress;
use IMEdge\Web\Device\Widget\Port\EthernetPort;
use IMEdge\Web\Grapher\GraphRendering\RrdImage;
use IMEdge\Web\Grapher\Structure\ExtendedRrdInfo;
use ipl\Html\BaseHtmlElement;
use ipl\Html\Html;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

class SnmpInterfaceCards extends BaseHtmlElement
{
    use TranslationHelper;

    protected $tag = 'section';

    protected $defaultAttributes = [
        'class' => ['inventory-cards'],
    ];

    protected UuidInterface $systemUuid;
    protected MacAddressBlockLookup $macLookup;
    /** @var array<string, ExtendedRrdInfo> */
    protected array $rrdFiles;
    /** @var array<string, ExtendedRrdInfo> */
    protected array $rrdOtherFiles;
    protected string $start;
    protected string $template;
    protected bool $adminUp = false;
    protected bool $operUp = false;
    protected Adapter $db;
    protected RrdImageLoader $imgLoader;

    public function __construct(Adapter $db, UuidInterface $systemUuid, string $template, string $start)
    {
        $this->db = $db;
        $this->start = $start;
        $this->imgLoader = new RrdImageLoader($db);
        $this->systemUuid = $systemUuid;
        $this->macLookup = new MacAddressBlockLookup($db);
        $this->template = $template;
    }

    public function filterAdminUp()
    {
        $this->adminUp = true;
    }

    public function filterOperUp()
    {
        $this->operUp = true;
    }

    protected function assemble()
    {
        $query = $this->prepareQuery();
        foreach ($this->db->fetchAll($query) as $row) {
            $this->add($this->renderCard($row));
        }
    }

    public function renderCard($row): BaseHtmlElement
    {
        $card = Html::tag('article', ['class' => 'card']);
        // Hint: imgFileName is being used by NetworkPortInfo
        if ($filename = $this->rrdFiles[$row->if_index] ?? null) {
            $row->imgFileName = null;
            // ImageLoader::getUrlWithTemplate($filename, $this->template, 640, 480, $this->graphDuration);
        } else {
            $row->imgFileName = null;
        }
        $cardClasses = [];

        if (IanaIfType::isEthernet((int) $row->if_type)) {
            $port = new EthernetPort();
            $width = $port->getFullWidth();
            $height = $port->getFullHeight();
            $svg = SvgUtils::createSvg2([
                // 'width' => $width * 1.5,
                // 'height' => $height * 1.5,
                'height' => '3em',
                'style' => 'position: relative; margin-top: -0.5em; margin-right: -1em;'
            ]);
            $svg->addAttributes([
                'viewBox' => "0 0 $width $height",
            ]);

            $svg->add($port);
            NetworkPortInfo::applyTo($port, $row);
        } else {
            $port = null;
            $svg = null;
        }
        if ($row->status_admin === 'up') {
            if ($row->status_oper === 'up') {
                $cardClasses[] = 'up';
            } else {
                $cardClasses[] = 'down';
            }
        } elseif ($row->status_admin === 'down') {
            $cardClasses[] = 'admin-down';
        } else {
            $cardClasses[] = 'admin-testing';
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

        $type = $row->if_type;

        if (isset(IanaIfType::TYPES[$type])) {
            $iconClass = ['class' => "state-icon $stateColor"];
            switch (IanaIfType::TYPES[$type]) {
                case 'other':
                    $type = Icon::create('help', $iconClass + ['title' => $this->translate('Other')]);
                    break;
                case 'ieee80216WMAN':
                    $type = Icon::create('wifi', $iconClass + ['title' => $this->translate('WiMAX')]);
                    break;
                case 'bridge':
                    $type = Icon::create('sitemap', $iconClass + ['title' => $this->translate('Bridge')]);
                    break;
                case 'softwareLoopback':
                    $type = Icon::create('arrows-cw', $iconClass + ['title' => $this->translate('Software Loopback')]);
                    break;
                default:
                    // $type = [$state ?? '', \sprintf('%s ', IanaIfType::TYPES[$type])];
                    $type = \sprintf('%s ', IanaIfType::TYPES[$type]);
            }
        }
        $url = Url::fromPath('imedge/snmp/snmp-interface', [
            'device_uuid' => Uuid::fromBytes($row->system_uuid)->toString(),
            'if_index'   => $row->if_index,
            'start'   => $this->start,
        ]);

        $card->addAttributes(['class' => $cardClasses]);
        $img1 = $this->imgLoader->getDeviceImg($this->systemUuid, 'if_traffic', $row->if_index, 'if_traffic');
        $img2 = $this->imgLoader->getDeviceImg($this->systemUuid, 'if_packets', $row->if_index, 'if_packets');
        $img3 = $this->imgLoader->getDeviceImg($this->systemUuid, 'if_error', $row->if_index, 'if_error');
        foreach ([$img1, $img2, $img3] as $img) {
            /** @var RrdImage $img */
            if ($img) {
                $img->loadImmediately();
                $img->graph->dimensions->set(360, 400);
                $img->graph->timeRange->setStart($this->start);
            }
        }
        if ($img2) {
            $img2->graph->dimensions->setHeight(60);
            $img2->graph->layout->disableXAxis();
        }
        if ($img3) {
            $img3->graph->dimensions->setHeight(60);
            $img3->graph->layout->disableXAxis();
        }
        $card->add([
            // $row->if_index,
            Html::tag('span', ['style' => 'float: right'], $svg ?: $type),
            // $row->connector_present,  // TODO: re-read
            // $row->status_duplex,
            Link::create(Html::tag('strong', sprintf('%s: %s', $this->translate('Traffic'), $row->if_name)), $url),
            $row->speed_kbit > 0 ? [Html::tag('br'), Format::bits($row->speed_kbit * 1000) . '/s'] : null,
            $img1,
            Html::tag('br'),
            Html::tag('strong', $this->translate('Packets')),
            ':',
            $img2,
            Html::tag('br'),
            Html::tag('strong', $this->translate('Errors')),
            ':',
            $img3,
            Html::tag('br'),
            ($row->if_alias && $row->if_alias !== $row->if_name) ? $this->singleLine($row->if_alias) : null,
            $this->singleLine($row->entity_description),
            ($row->physical_address ? [MacAddress::fromBinary($row->physical_address, $this->macLookup)] :  null),
            $row->mtu > 0
                ? [Html::tag('br'), 'MTU ' . $row->mtu]
                : null,
        ]);

        return $card;
    }

    protected function singleLine($content)
    {
        if ($content === '' || $content === null) {
            return null;
        }

        return Html::tag('span', ['class' => 'one-line'], $content);
    }

    public function prepareQuery()
    {
        // TODO: if no agent -> join all of them, add columns
        $query = $this->db->select()
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
            ->order('if_index')
        ;
        if ($this->adminUp) {
            $query->where('i.status_admin = ?', 'up');
        }
        if ($this->operUp) {
            $query->where('s.status_operational = ?', 'up');
        }

        return $query;
    }
}
