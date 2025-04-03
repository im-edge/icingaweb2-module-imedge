<?php

namespace Icinga\Module\Imedge\Web\Form\Discovery;

use gipfl\IcingaWeb2\Url;
use gipfl\Translation\TranslationHelper;
use Icinga\Module\Imedge\Discovery\DiscoveryRuleImplementation;
use Icinga\Module\Imedge\Discovery\SeedFileNediStyle;
use Icinga\Module\Imedge\Web\Form\UuidObjectForm;
use IMEdge\Config\Settings;
use IMEdge\Json\JsonString;
use IMEdge\Web\Data\Model\DiscoveryRule;
use IMEdge\Web\Data\RemoteLookup\SnmpCredentialLookup;
use IMEdge\Web\Select2\FormElement\SelectRemoteElement;

class DiscoveryRuleForm extends UuidObjectForm
{
    use TranslationHelper;

    protected static ?array $modelProperties = null;
    protected string $modelClass = DiscoveryRule::class;
    protected $keyProperty = 'uuid';

    protected function getModelProperties(): array
    {
        if (self::$modelProperties === null) {
            $class = $this->modelClass;
            $self = new $class();
            self::$modelProperties = array_keys($self->getDefaultProperties());
        }

        return self::$modelProperties;
    }

    protected function assemble()
    {
        $this->addFormElements();
        $this->addButtons();
    }

    protected function addFormElements()
    {
        $this->addElement('text', 'label', [
            'label' => $this->translate('Label'),
            'required' => true,
            'class' => 'autofocus',
        ]);
        $this->addElement(new SelectRemoteElement('credential_uuid', [
            'label'           => $this->translate('SNMP Credential'),
            'data-lookup-url' => Url::fromPath('imedge/lookup/snmp-credential'),
            'lookup'          => new SnmpCredentialLookup($this->store->getDb()),
            'class'           => 'autosubmit',
            'required'        => true,
        ]));
        $this->addElement('select', 'implementation', [
            'label' => $this->translate('IP Address Source'),
            'options' => [
                null => $this->translate('- please choose -'),
                SeedFileNediStyle::class => $this->translate('Seed File (NeDi-Style)')
            ],
            'class' => 'autosubmit',
            'required' => true,
        ]);

        if ($this->getValue('implementation')) {
            $instance = $this->createInstance();
            $instance->extendForm($this);
        }
    }

    public function createInstance(): DiscoveryRuleImplementation
    {
        return DiscoveryRuleImplementation::createInstance(
            $this->getValue('implementation'),
            $this->getSettings()
        );
    }

    protected function getSettings(): Settings
    {
        $settings = $this->getValues();
        foreach (self::getModelProperties() as $property) {
            unset($settings[$property]);
        }

        return Settings::fromSerialization($settings);
    }

    protected function getMainValues(): array
    {
        $allowed = array_flip(self::getModelProperties());
        $values = [];
        foreach ($this->getValues() as $key => $value) {
            if (array_key_exists($key, $allowed)) {
                $values[$key] = $value;
            }
        }

        return $values;
    }

    public function populate($values)
    {
        if (isset($values['settings'])) {
            $settings = Settings::fromSerialization(JsonString::decode($values['settings']));
            unset($values['settings']);
            foreach ((array) $settings->jsonSerialize() as $key => $value) {
                $values[$key] = $value;
            }
        }

        parent::populate($values);
    }

    public function onSuccess()
    {
        $instance = $this->createInstance(); // Unused, instantiated for validation purposes
        $values = $this->getMainValues();
        $values['settings'] = JsonString::encode($this->getSettings());
        $this->succeedWithValues($values);
        /*
        return;
        foreach ($instance->getResolvedCandidates() as $candidate) {
            DiscoveryCandidate::create([
                'uuid'                => Uuid::uuid4(),
                'discovery_rule_uuid' => $this->null,
                'datanode_uuid'       => null,
                'credential_uuid'     => null,
                'ip_address'          => null,
                'snmp_port'           => null,
                'state'               => null,
                'ts_last_reachable'   => null,
                'ts_last_check'       => null,

            ]);
        }
        exit;
        $this->addHidden('settings', JsonString::encode($settings));
        */
    }
}
