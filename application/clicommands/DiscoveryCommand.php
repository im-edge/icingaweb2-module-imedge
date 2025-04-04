<?php

namespace Icinga\Module\Imedge\CliCommands;

use Icinga\Application\Logger;
use Icinga\Cli\Command;
use Icinga\Module\Imedge\Controllers\DbTrait;
use Icinga\Module\Imedge\Discovery\DiscoveryRuleImplementation;
use Icinga\Module\Imedge\NodeControl\TargetShipper;
use IMEdge\Config\Settings;
use IMEdge\Json\JsonString;
use IMEdge\Web\Rpc\IMEdgeClient;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

use function Clue\React\Block\await;

class DiscoveryCommand extends Command
{
    use DbTrait;

    /**
     * Show all SNMP Discovery Rule candidates
     *
     * USAGE
     *
     * icingacli imedge discovery candidates --rule 'Rule Name'
     */
    public function candidatesAction(): void
    {
        $ruleName = $this->params->getRequired('rule');
        $rule = $this->db()->fetchRow(
            $this->db()->select()->from('snmp_discovery_rule')->where('label = ?', $ruleName)
        );
        if (! $rule) {
            $this->fail('There is no such rule: ' . $ruleName);
        }
        $instance = DiscoveryRuleImplementation::createInstance(
            $rule->implementation,
            Settings::fromSerialization(JsonString::decode($rule->settings))
        );
        foreach ($instance->getCandidates() as $candidate) {
            echo "$candidate\n";
        }
    }

    /**
     * Trigger SNMP Discovery Rule
     *
     * USAGE
     *
     *     icingacli imedge discovery run --rule 'Rule Name' --node imedge.example.com \
     *         [--auto-inventory] [--verbose] [--environment <name>] [--lifecycle <name>]
     */
    public function runAction(): void
    {
        $ruleName = $this->params->getRequired('rule');
        $rule = $this->db()->fetchRow(
            $this->db()->select()->from('snmp_discovery_rule')->where('label = ?', $ruleName)
        );
        if (! $rule) {
            $this->fail('There is no such rule: ' . $ruleName);
        }
        $shipper = new TargetShipper($this->db());
        $shipper->shipCredentials($this->getDataNodeUuid());

        $instance = DiscoveryRuleImplementation::createInstance(
            $rule->implementation,
            Settings::fromSerialization(JsonString::decode($rule->settings))
        );
        $client = $this->client();
        $autoInventory = $this->params->get('auto-inventory');

        $jobId = await($client->request('snmp.scanRanges', [
            Uuid::fromBytes($rule->credential_uuid),
            $instance->getTargetGeneratorClass(),
            $instance->getSettings()
        ]));
        register_shutdown_function(function () use ($client, $jobId) {
            await($client->request('snmp.deleteDiscoveryJobResults', [$jobId]));
        });
        $offset = '0-0';
        while (true) {
            $response = await($client->request('snmp.streamDiscoveryJobResults', [$jobId, 1000, $offset]));
            if ($response->job) {
                if ($response->job->status === 'finished') {
                    // Logger::info('JOB status is finished');
                    break;
                }
            } else {
                // Logger::info('There is no such job');
                break;
            }
            $offset = $response->offset;
            $created = 0;
            foreach ($response->results as $result) {
                if ($autoInventory) {
                    if ($this->createAgentIfNew($result)) {
                        $created++;
                    }
                } else {
                    Logger::info(sprintf(
                        'SNMP device has been discovered at %s: %s',
                        $result->peer,
                        $result->label
                    ));
                }
            }
            if ($created > 0) {
                $shipper->shipTargets($this->getDataNodeUuid());
            }
        }
    }

    protected function createAgentIfNew($result)
    {
        if ($this->getExistingAgentUuid($result->peer)) {
            return false;
        }

        try {
            $this->createSnmpAgent($result);
            Logger::info(sprintf(
                'New SNMP agent has been created for %s (%s)',
                $result->peer,
                $result->label
            ));
            return true;
        } catch (\Exception $e) {
            Logger::error(sprintf(
                'Failed to create new SNMP agent for %s (%s): %s',
                $result->peer,
                $result->label,
                $e->getMessage()
            ));
        }
        return false;
    }

    protected function createSnmpAgent($result)
    {
        $db = $this->db();
        $defaultLifecycle = Uuid::fromString('7d214595-d096-5032-b7de-7615e4464b40'); // Maintenance
        $defaultEnvironment = Uuid::fromString('b8ac9370-7916-500d-8d5f-49a769f51ad4'); // Production
        if ($lifecycle = $this->params->get('lifecycle')) {
            $lifecycle = Uuid::fromBytes($db->fetchOne(
                $db->select()->from('system_lifecycle', 'uuid')->where('label = ?', $lifecycle)
            ));
        } else {
            $lifecycle = $defaultLifecycle;
        }
        if ($environment = $this->params->get('environment')) {
            $environment = Uuid::fromBytes($db->fetchOne(
                $db->select()->from('system_environment', 'uuid')->where('label = ?', $environment)
            ));
        } else {
            $environment = $defaultEnvironment;
        }
        $agent = [
            'agent_uuid'       => Uuid::uuid4()->getBytes(),
            'credential_uuid'  => Uuid::fromString($result->credential)->getBytes(),
            'datanode_uuid'    => $this->getDataNodeUuid()->getBytes(),
            'lifecycle_uuid'   => $lifecycle->getBytes(),
            'environment_uuid' => $environment->getBytes(),
            'ip_protocol'      => 'ipv4',
        ];
        [$ip, $port] = explode(':', $result->peer); // TODO: IPv6
        $agent['ip_address'] = inet_pton($ip);
        $agent['snmp_port'] = (int) $port;

        return $db->insert('snmp_agent', $agent) > 0;
    }

    protected function getExistingAgentUuid(string $peer): ?UuidInterface
    {
        $db = $this->db();
        [$ip, $port] = explode(':', $peer); // TODO: IPv6, strrpos
        $query = $db->select()
            ->from('snmp_agent', 'agent_uuid')
            ->where('ip_address = ?', inet_pton($ip))
            ->where('snmp_port = ?', (int) $port);

        if ($uuid = $db->fetchOne($query)) {
            return Uuid::fromBytes($uuid);
        }

        return null;
    }

    protected function client(): IMEdgeClient
    {
        return (new IMEdgeClient())->withTarget($this->getDataNodeUuid()->toString());
    }

    protected function getDataNodeUuid(): UuidInterface
    {
        $db = $this->db();
        $nodeName = $this->params->getRequired('node');
        return Uuid::fromBytes(
            $db->fetchOne($db->select()->from('datanode', 'uuid')->where('label = ?', $nodeName))
        );
    }
}
