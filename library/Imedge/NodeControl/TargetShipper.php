<?php

namespace Icinga\Module\Imedge\NodeControl;

use gipfl\ZfDb\Adapter\Adapter;
use IMEdge\Web\Data\Model\SnmpAgent;
use IMEdge\Web\Data\Model\SnmpCredential;
use IMEdge\Web\Rpc\IMEdgeClient;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

use function Clue\React\Block\await;

class TargetShipper
{
    protected Adapter $db;
    protected array $clients = [];

    public function __construct(Adapter $db)
    {
        $this->db = $db;
    }

    public function clearTargets(UuidInterface $datanodeUuid)
    {
        return $this->targetedRequest($datanodeUuid, 'snmp.setKnownTargets', (object) [
            'targets' => [],
        ]);
    }

    public function shipTargets(UuidInterface $datanodeUuid)
    {
        return $this->targetedRequest($datanodeUuid, 'snmp.setKnownTargets', (object) [
            'targets' => $this->getTargetsForDatanode($datanodeUuid),
        ]);
    }

    public function shipCredentials(UuidInterface $datanodeUuid)
    {
        return $this->targetedRequest($datanodeUuid, 'snmp.setCredentials', (object) [
            'credentials' => $this->getCredentialsForDatanode($datanodeUuid),
        ]);
    }

    protected function getTargetsForDatanode(UuidInterface $datanodeUuid): array
    {
        $db = $this->db;
        $query = $db->select()->from(['a' => SnmpAgent::TABLE], [
            'a.agent_uuid',
            'a.ip_address',
            'a.snmp_port',
            'a.credential_uuid'
        ])->join(
            ['lc' => 'system_lifecycle'],
            "lc.uuid = a.lifecycle_uuid AND (lc.enable_monitoring = 'y' OR lc.enable_discovery = 'y')",
            []
        )->where('a.datanode_uuid = ?', $datanodeUuid->getBytes())
        ->order('ip_address');
        // ->limit(10)
        $agents = $db->fetchAll($query);

        $targets = [];
        foreach ($agents as $agent) {
            $identifier = Uuid::fromBytes($agent->agent_uuid)->toString();
            $targets[$identifier] = (object) [
                'identifier' => $identifier,
                'address' => (object) [
                    'ip'   => inet_ntop($agent->ip_address),
                    'port' => $agent->snmp_port,
                ],
                'credentialUuid' => Uuid::fromBytes($agent->credential_uuid)
            ];
        }

        return $targets;
    }

    protected function getCredentialsForDatanode(UuidInterface $datanodeUuid): array
    {
        $credentials = [];
        $db = $this->db;
        $rows = $db->fetchAll(
            $db->select()->distinct()->from(['c' => SnmpCredential::TABLE], 'c.*')
                ->join(['a' => SnmpAgent::TABLE], 'c.credential_uuid = a.credential_uuid', [])
                ->where('a.datanode_uuid = ?', $datanodeUuid->getBytes())
        );
        foreach ($rows as $row) {
            $credentials[] = SnmpCredential::create((array) $row);
        }

        return $credentials;
    }

    protected function targetedRequest(UuidInterface $uuid, $method, $params = [])
    {
        $key = $uuid->toString();
        $this->clients[$key] ??= (new IMEdgeClient())->withTarget($key);

        return await($this->clients[$key]->request($method, $params), null, 32);
    }
}
