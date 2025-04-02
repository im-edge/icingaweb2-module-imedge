<?php

namespace Icinga\Module\Imedge\Web\Form\Discovery;

use gipfl\Translation\TranslationHelper;
use gipfl\Web\Form;
use Ramsey\Uuid\Uuid;

class DiscoveryForm extends Form
{
    use TranslationHelper;

    protected $result;
    protected $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    protected function optionalEnum($list): array
    {
        return [
            null   => $this->translate('- please choose -'),
        ] + $list;
    }

    protected function assemble()
    {
        $this->addElement('select', 'protocol', [
            'label'        => $this->translate('Protocol'),
            'options' => $this->optionalEnum([
                'icmp' => $this->translate('ICMP Echo'),
                'tcp'  => $this->translate('TCP Port(s)'),
                'snmp' => $this->translate('SNMP'),
            ]),
            'class' => 'autosubmit'
        ]);

        $protocol = $this->getValue('protocol');
        if ($protocol === null) {
            $this->addElement('submit', 'submit', [
                'label' => $this->translate('Next')
            ]);
            return;
        }

        switch ($protocol) {
            case 'tcp':
                $this->addElement('text', 'subnet', [
                    'label' => 'Subnet'
                ]);
                $this->addElement('text', 'ports', [
                    'label' => 'Port(s)'
                ]);
                break;
            case 'icmp':
                $this->addElement('text', 'subnet', [
                    'label' => 'Subnet'
                ]);
                break;
            case 'snmp':
                $this->addElement('text', 'subnet', [
                    'label' => 'Subnet'
                ]);
                $this->addElement('select', 'credential', [
                    'label'   => 'Credential',
                    'multiOptions' => $this->optionalEnum($this->listCredentials())
                ]);
                break;
        }

        $this->addElement('submit', 'submit', [
            'label' => $this->translate('Scan')
        ]);
    }

    protected function listCredentials()
    {
        $db = $this->db;
        $result = [];
        $credentials = $db->fetchPairs($db->select()->from('snmp_credential', [
            'credential_uuid',
            'credential_name',
        ])->order('credential_name'));
        foreach ($credentials as $uuid => $name) {
            $result[Uuid::fromBytes($uuid)->toString()] = $name;
        }

        return $result;
    }

    public function getResult()
    {
        return $this->result;
    }

    public function onSuccess()
    {
        $this->result = $this->getValues();
    }
}
