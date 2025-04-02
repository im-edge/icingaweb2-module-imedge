<?php

namespace Icinga\Module\Imedge\NodeControl;

use gipfl\ZfDb\Adapter\Pdo\PdoAdapter;
use IMEdge\Web\Rpc\IMEdgeClient;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

use function Clue\React\Block\await;

/**
 * Required, as we do not have them in our DB
 */
class MetricStoreLookup
{
    protected PdoAdapter $db;
    protected static ?array $metricStores = null;

    public function __construct(PdoAdapter $db)
    {
        $this->db = $db;
    }

    public function getMetricStorePath(UuidInterface $uuid): ?string
    {
        if ($store = $this->getMetricStore($uuid)) {
            return $store->path;
        }

        return null;
    }

    public function getMetricStoreNodeName(UuidInterface $uuid): ?string
    {
        if ($store = $this->getMetricStore($uuid)) {
            return $store->nodeName;
        }

        return null;
    }

    public function getMetricStoreNodeUuid(UuidInterface $uuid): UuidInterface
    {
        if ($store = $this->getMetricStore($uuid)) {
            return $store->nodeUuid;
        }

        throw new \Exception('Unable to talkto referenced node');
    }

    public function getMetricStoreName(UuidInterface $uuid): ?string
    {
        if ($store = $this->getMetricStore($uuid)) {
            return $store->name;
        }

        return null;
    }

    protected function getMetricStore(UuidInterface $uuid)
    {
        return $this->getMetricStores()[$uuid->getBytes()] ?? null;
    }

    protected function getMetricStores(): array
    {
        return self::$metricStores ?? $this->fetchMetricStores();
    }

    protected function fetchMetricStores()
    {
        $dataNodes = $this->db->fetchAll('SELECT * FROM datanode');
        $stores = [];
        foreach ($dataNodes as $node) {
            try {
                $nodeUuid = Uuid::fromBytes($node->uuid);
                $client = (new IMEdgeClient())->withTarget($nodeUuid->toString());
                $nodeName = await($client->request('node.getName'));
                $myStores = await($client->request('metrics.getStores'));
                foreach ($myStores as $myStore) {
                    $stores[Uuid::fromString($myStore->uuid)->getBytes()] = (object) [
                        'uuid' => $myStore->uuid,
                        'name' => $myStore->name,
                        'path' => $myStore->path,
                        'nodeUuid' => Uuid::fromBytes($node->uuid)->toString(),
                        'nodeName' => $nodeName,
                    ];
                }
            } catch (\Exception $e) {
            }
        }

        return $stores;
    }
}
