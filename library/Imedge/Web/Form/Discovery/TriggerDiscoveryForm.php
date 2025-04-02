<?php

namespace Icinga\Module\Imedge\Web\Form\Discovery;

use gipfl\Translation\TranslationHelper;
use gipfl\ZfDb\Adapter\Adapter;
use gipfl\ZfDbStore\ZfDbStore;
use Icinga\Module\Imedge\Discovery\DiscoveryRuleImplementation;
use Icinga\Module\Imedge\Web\Form\Form;
use IMEdge\Web\Rpc\IMEdgeClient;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

use function Amp\async;
use function Clue\React\Block\await;

class TriggerDiscoveryForm extends Form
{
    use TranslationHelper;

    protected ?UuidInterface $nodeUuid;
    protected Adapter $db;
    protected ZfDbStore $store;
    public ?DiscoveryRuleImplementation $ruleImplementation = null;
    public ?int $jobId = null;

    public function __construct(?UuidInterface $nodeUuid, ZfDbStore $store)
    {
        $this->nodeUuid = $nodeUuid;
        $this->store = $store;
    }

    protected function assemble()
    {
        $this->addElement('select', 'node_uuid', [
            'label'    => $this->translate('IMEdge Node'),
            'options'  => $this->enum('datanode'),
            'class'    => 'autosubmit',
            'value'    => $this->nodeUuid ? $this->nodeUuid->toString() : null,
            'required' => true,
        ]);
        $this->addElement('select', 'rule_uuid', [
            'label'    => $this->translate('Discovery Rule'),
            'options'  => $this->enum('snmp_discovery_rule'),
            'class'    => 'autosubmit',
            'required' => true,
        ]);
        $ruleUuid = $this->getValue('rule_uuid');
        $nodeUuid = $this->getValue('node_uuid');
        if ($nodeUuid && $ruleUuid) {
            $ruleForm = new DiscoveryRuleForm($this->store, Uuid::fromString($ruleUuid));
            $ruleForm->handleRequest($this->getRequest()); // Trigger populate etc
            $this->ruleImplementation = $ruleForm->createInstance();
        }
        $this->addElement('submit', 'submit', [
            'label' => $this->translate('Run now')
        ]);
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

    protected function onSuccess()
    {
        $client = (new IMEdgeClient())->withTarget($this->nodeUuid->toString());
        $this->jobId = await($client->request('snmp.scanRanges', [
            '0dfd7755-85fb-4683-8c8a-9790497820ed',
            $this->ruleImplementation->getTargetGeneratorClass(),
            $this->ruleImplementation->getSettings()
        ]));
    }
}
