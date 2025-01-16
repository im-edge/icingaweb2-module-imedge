<?php

namespace Icinga\Module\Imedge\Web\Widget\Snmp;

use gipfl\Translation\TranslationHelper;
use gipfl\Web\Table\NameValueTable;
use Icinga\Module\Imedge\Web\Enrichment\NetworkPortInfo;
use Icinga\Util\Format;
use IMEdge\Svg\SvgUtils;
use IMEdge\Web\Data\ForeignModel\IanaIfType;
use IMEdge\Web\Data\Lookup\MacAddressBlockLookup;
use IMEdge\Web\Data\Model\NetworkInterfaceConfig;
use IMEdge\Web\Data\Model\NetworkInterfaceStatus;
use IMEdge\Web\Data\Widget\MacAddress;
use IMEdge\Web\Device\Widget\Port\EthernetPort;
use ipl\Html\BaseHtmlElement;
use ipl\Html\Html;

class InterfaceDetails extends BaseHtmlElement
{
    use TranslationHelper;

    protected $tag = 'div';
    protected NetworkInterfaceConfig $ifConfig;
    protected ?NetworkInterfaceStatus $ifStatus;
    protected BaseHtmlElement $controls;
    protected MacAddressBlockLookup $macLookup;
    /**
     * @var float|int|null
     */
    protected $duration;

    public function __construct(
        BaseHtmlElement $controls,
        MacAddressBlockLookup $macLookup,
        NetworkInterfaceConfig $ifconfig,
        ?NetworkInterfaceStatus $ifStatus = null
    ) {
        $this->macLookup = $macLookup;
        $this->ifConfig = $ifconfig;
        $this->ifStatus = $ifStatus;
        $this->controls = $controls;
        if (IanaIfType::isEthernet((int) $ifconfig->get('if_type'))) {
            $this->controls->prepend($this->prepareEthernetPortSvg());
            // $this->add($this->prepareEthernetPortSvg());
        }
    }

    protected function assemble(): void
    {
        $if = $this->ifConfig;
        $ifs = $this->ifStatus;

        if (IanaIfType::isEthernet((int) $if->get('if_type'))) {
            $this->controls->prepend($this->prepareEthernetPortSvg());
        }
        $ifConfig = new NameValueTable();
        $ifConfig->addNameValuePairs([
            null => Html::tag('h3', $this->translate('Configuration')),
            $this->translate('Configured Status') => $if->get('status_admin'),
            $this->translate('Name') => $if->get('if_name'),
            $this->translate('Description') => $if->get('if_description'),
            $this->translate('Alias') => $if->get('if_alias'),
            $this->translate('Index') => $if->get('if_index'),
            $this->translate('Type') => IanaIfType::describe((int) $if->get('if_type'))
                . ' (' . $if->get('if_type') . ')',
            $this->translate('Monitor') => $if->get('monitor'),
            $this->translate('Notify') => $if->get('notify'),
        ]);
        $ifStatus = new NameValueTable();
        $ifStatus->addNameValuePairs([
            null => Html::tag('h3', $this->translate('Status')),
            $this->translate('Operational Status') => $ifs ? $ifs->get('status_operational') : '-',
            $this->translate('Duplex Status') => $ifs ? $ifs->get('status_duplex') : '-',
            $this->translate('Physical Connector') => $ifs ? $ifs->get('connector_present') : '-',
            // $this->translate('Usage') => $ifs->get('current_kbit_in') / out
            $this->translate('Speed') => Format::bits($if->get('speed_kbit') * 1000) . '/s',
            $this->translate('MTU Size') => $if->get('mtu'),
            $this->translate('MAC Address') => $this->showMacAddress($if->get('physical_address')),
        ]);
        $divAttrs = [
            'style' => 'display: inline-block; width: 33%; vertical-align: top;'
        ];
        $this->add([
            Html::tag('div', $divAttrs, $ifConfig),
            Html::tag('div', $divAttrs, $ifStatus),
        ]);
        if ($ifs === null) {
            return;
        }
        $this->add([
            Html::tag('div', $divAttrs, $this->stpStatusInfo($ifs)),
        ]);
    }

    protected function stpStatusInfo(NetworkInterfaceStatus $status): ?NameValueTable
    {
        if (empty($status->get('status_stp'))) {
            return null;
        }

        return (new NameValueTable())->addNameValuePairs([
            null => Html::tag('h3', $this->translate('Spanning Tree')),
            $this->translate('Status')              => $status->get('status_stp'),
            $this->translate('Designated Root')     => $this->showBridgeId($status->get('stp_designated_root')),
            $this->translate('Designated Bridge')   => $this->showBridgeId($status->get('stp_designated_bridge')),
            $this->translate('Designated Port')     => $status->get('stp_designated_port'),
            $this->translate('Forward Transitions') => $status->get('stp_forward_transitions'),
            $this->translate('Port Path Cost')      => $status->get('stp_port_path_cost'),
        ]);
    }

    protected function showBridgeId($bridgeId): ?array
    {
        if ($bridgeId === null || \strlen($bridgeId) === 0) {
            return null;
        }
        $prio = \substr($bridgeId, 0, 2);
        // var_dump(base64_encode($prio));
        // var_dump(unpack('n*', $prio)); exit;
        return [/*$prio, */ MacAddress::fromBinary(substr($bridgeId, 2), $this->macLookup)];
    }

    protected function showMacAddress(?string $binaryMac): ?array
    {
        if ($binaryMac === null || \strlen($binaryMac) === 0) {
            return null;
        }
        $mac = MacAddress::fromBinary($binaryMac, $this->macLookup);
        return [
            $mac,
            Html::tag('br'),
            $mac->description,
            $mac->additionalInfo ? [
                Html::tag('br'),
                $mac->additionalInfo
            ] : null,
        ];
    }

    protected function getCompatIfStatusRow(): object
    {
        $if = $this->ifConfig;
        $ifs = $this->ifStatus;

        $fakeRow = (object) $if->getProperties();
        $fakeRow->id = $fakeRow->if_index;
        $fakeRow->if_status_admin = $fakeRow->status_admin;
        $fakeRow->if_status_oper = $ifs->get('status_operational') ?? null;
        $fakeRow->if_status_duplex = $ifs->get('status_duplex') ?? null;
        $fakeRow->if_status_stp = $ifs->get('status_stp') ?? null;
        $fakeRow->if_speed_kbit = $fakeRow->speed_kbit;
        $fakeRow->if_usage_out = $ifs ? $ifs->get('current_kbit_out') : null;
        $fakeRow->if_usage_in = $ifs ? $ifs->get('current_kbit_in') : null;
        $fakeRow->relative_position = null;

        return $fakeRow;
    }

    protected function prepareEthernetPortSvg(): BaseHtmlElement
    {
        $port = new EthernetPort();
        $width = $port->getFullWidth();
        $height = $port->getFullHeight();
        $svg = SvgUtils::createSvg2([
            // 'width' => $width * 1.5,
            // 'height' => $height * 1.5,
            'height' => '4em',
            'style' => 'right: 1em; position: absolute;'
        ]);
        $svg->addAttributes([
            'viewBox' => "0 0 $width $height",
        ]);
        $svg->add($port);
        NetworkPortInfo::applyTo($port, $this->getCompatIfStatusRow());
        // header('Content-Type: image/svg+xml');
        // echo $svg; exit;

        return Html::tag('div', ['style' => 'max-height: 1em;'], $svg);
    }
}
