<?php

namespace Icinga\Module\Imedge\Web\Form\Snmp;

use gipfl\IcingaWeb2\Url;
use gipfl\Translation\TranslationHelper;
use Icinga\Module\Imedge\Web\Form\UuidObjectForm;
use IMEdge\Web\Data\Model\SnmpAgent;
use IMEdge\Web\Data\RemoteLookup\DatanodeLookup;
use IMEdge\Web\Data\RemoteLookup\SnmpCredentialLookup;
use IMEdge\Web\Select2\FormElement\SelectRemoteElement;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

class SnmpAgentForm extends UuidObjectForm
{
    use TranslationHelper;

    protected string $modelClass = SnmpAgent::class;
    protected $keyProperty = 'agent_uuid';
    protected string $defaultLifecycle = '7d214595-d096-5032-b7de-7615e4464b40'; // Maintenance
    protected string $defaultEnvironment = 'b8ac9370-7916-500d-8d5f-49a769f51ad4'; // Production

    protected function assemble()
    {
        $this->addFormElements();
        $this->addButtons();
    }

    public function populate($values)
    {
        if (isset($values['ip_address'])) {
            $printable = @inet_ntop($values['ip_address']);
            if ($printable) {
                $values['ip_address'] = $printable;
            }
        }

        parent::populate($values);
    }

    protected function addFormElements()
    {
        $this->addElement('text', 'ip_address', [
            'label' => $this->translate('IP Address'),
            'required' => true,
            'class' => 'autofocus',
        ]);
        $this->addElement('number', 'snmp_port', [
            'label' => $this->translate('SNMP Port'),
            'value' => 161,
            'required' => true,
        ]);
        // Lookup form, as there might be thousands of credentials
        $this->addElement(new SelectRemoteElement('credential_uuid', [
            'label'           => $this->translate('SNMP Credential'),
            'data-lookup-url' => Url::fromPath('imedge/lookup/snmp-credential'),
            'lookup'          => new SnmpCredentialLookup($this->store->getDb()),
            'class'           => 'autosubmit',
            'required'        => true,
        ]));
        $this->addElement(new SelectRemoteElement('datanode_uuid', [
            'label'           => $this->translate('Datanode'),
            'data-lookup-url' => Url::fromPath('imedge/lookup/node'),
            'lookup'          => new DatanodeLookup($this->store->getDb()),
            'class'           => 'autosubmit',
            'required'        => true,
        ]));
        $this->addElement('select', 'lifecycle_uuid', [
            'label' => $this->translate('Lifecycle'),
            'options' => $this->enum('system_lifecycle'),
            'value'   => $this->defaultLifecycle,
            'required' => true,
        ]);
        $this->addElement('select', 'environment_uuid', [
            'label' => $this->translate('Environment'),
            'options' => $this->enum('system_environment'),
            'value'   => $this->defaultEnvironment,
            'required' => true,
        ]);
        $this->addElement('text', 'label', [
            'label' => $this->translate('Label'),
            'description' => $this->translate(
                'Optional, set only in case it cannot be obtained reliable from the device or via DNS'
            ),
        ]);
    }

    public function getDatanodeUuid(): UuidInterface
    {
        return Uuid::fromString($this->getValue('datanode_uuid'));
    }

    public function onSuccess()
    {
        $values = $this->getValues();
        $this->addHidden('ip_protocol', 'ipv4');
        $binaryIp = inet_pton($values['ip_address']);
        if ($binaryIp === false) {
            throw new \RuntimeException(sprintf("'%s' is not a valid IP address", $values['ip_address']));
        }
        if (strlen($binaryIp) === 4) {
            $values['ip_protocol'] = 'ipv4';
        } else {
            $values['ip_protocol'] = 'ipv6';
        }
        $values['ip_address'] = $binaryIp;
        $new = $this->instance->isNew();
        $this->succeedWithValues($values);
        /*
        if ($new) {
            $sysInfo = SnmpSystemInfo::create([
                'uuid' => $this->instance->get('agent_uuid'),
                'datanode_uuid' => $values['datanode_uuid'],
            ]);
            $this->store->store($sysInfo);
        }
        */
    }

    protected function enum($table, $uuidColumn = 'uuid', $labelColumn = 'label'): array
    {
        $db = $this->store->getDb();
        $values = [];
        foreach ($db->fetchPairs($db->select()->from($table, [$uuidColumn, $labelColumn])) as $uuid => $label) {
            $values[Uuid::fromBytes($uuid)->toString()] = $label;
        }

        return [null => $this->translate('- please choose -')] + $values;
    }
}
