<?php

namespace Icinga\Module\Imedge\Web\Form\Rpc;

use gipfl\Translation\TranslationHelper;
use gipfl\Web\Form;

class LiveSnmpScenarioForm extends Form
{
    use TranslationHelper;
    protected $defaultDecoratorClass = null;
    protected $useCsrf = false;
    protected $useFormName = false;

    public function __construct()
    {
        $this->setMethod('GET');
    }

    protected function assemble()
    {
        $this->addElement('select', 'scenarioName', [
            'options' => [
                null => '- please choose -',
                'sysInfo'          => 'System Information',

                'interfaceConfig'  => 'Interface Config',
                'interfaceStack'   => 'Interface Stack',
                'interfaceStatus'  => 'Interface Status',

                'interfaceTraffic' => 'Interface Traffic',
                'interfacePacket'  => 'Interface Packets',
                'interfaceError'   => 'Interface Errors',

                'ipAddressTable'   => 'IP Address Table',

                'bgp4Peers'         => 'BGPv4 Peers',
                'filesystems'       => 'File Systems',
                'storage'           => 'Storage',
                'softwareInstalled' => 'Installed Software',
                'processList' => 'Process List',

                'entity'      => 'Entity',
                'entityIfMap' => 'Entity/Interface Mapping',

                'sensors' => 'Sensors',


                'cdpConfig' => 'CDP Config',
                'cdpCache'  => 'CDP Cache',

                'lmFanSensors' => 'LM Fan Sensors',
                'lmTempSensors' => 'LM Temperature Sensors',
                'lmVoltSensors' => 'LM Volt Sensors',

                'icomBsTsConfig' => 'ICOM BS/TS Configuration',
                'icomBsTsStatus' => 'ICOM BS/TS Status',
                'icomSensors'    => 'ICOM Sensors',
            ],
            'class' => ['autosubmit', 'autofocus'],
        ]);
        $this->addElement('select', 'resultType', [
            'options' => [
                'snmp'   => $this->translate('Show SNMP result'),
                'object' => $this->translate('Show scenario object'),
            ],
            'class' => ['autosubmit'],
        ]);
    }
}
