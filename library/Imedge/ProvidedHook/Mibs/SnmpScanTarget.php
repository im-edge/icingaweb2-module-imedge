<?php

namespace Icinga\Module\Imedge\ProvidedHook\Mibs;

use gipfl\ZfDb\Adapter\Adapter;
use Icinga\Module\Imedge\Db\DbFactory;
use Icinga\Module\Imedge\NodeControl\RemoteSnmpClient;
use Icinga\Module\Mibs\Hook\SnmpScanTargetHook;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

class SnmpScanTarget extends SnmpScanTargetHook
{
    protected Adapter $db;

    public function __construct()
    {
        $this->db = DbFactory::db();
    }

    public function enumTargets(): array
    {
        $db = $this->db;
        $query = $db->select()->from(['a' => 'snmp_agent'], [
            'a.agent_uuid',
            'label' => 'COALESCE(a.label, si.system_name)',
            'a.ip_address',
            'a.snmp_port',
            // 'manufacturer_name',
            // 'model_name', -> TODO: JOIN device
        ])->joinLeft(['si' => 'snmp_system_info'], 'si.uuid = a.agent_uuid', [])
            // ->order('sys_name')
            // ->where('datanode_uuid = ?', hex2bin('d84c26ac6f1c4020880bba98abf2ed42'))
            ->order('COALESCE(a.label, si.system_name)')
            ->order('ip_address')
        ;
/*
        $query = $db->select()->from('snmp_discovery_candidate', [
            'uuid',
            'ip_address',
            'snmp_port',
        ])->where('datanode_uuid = ?', hex2bin('d84c26ac6f1c4020880bba98abf2ed42'))
            ->order('ip_address');
*/

        $result = [];
        foreach ($db->fetchAll($query) as $row) {
            $label = $row->label ?? '-';
            if ($row->ip_address !== null) {
                $label .= ' (' . inet_ntop($row->ip_address);
                if ($row->snmp_port !== 161) {
                    $label .= ':' . $row->snmp_port;
                }
                $label .= ')';
            }

            // Not yet
            if (($row->manufacturer_name ?? null) !== null) {
                $label .= ' - ' . $row->manufacturer_name;
            }
            if (($row->model_name ?? null) !== null) {
                $label .= ' - ' . $row->model_name;
            }
            $result[Uuid::fromBytes($row->agent_uuid ?? $row->uuid)->toString()] = $label;
        }

        return $result;
    }

    protected function requireTarget(string $targetIdentifier): \stdClass
    {
        $db = $this->db;
        $uuid = Uuid::fromString($targetIdentifier);
        $query = $db->select()->from('snmp_agent', [
            'uuid' => 'agent_uuid',
            'credential_uuid',
            'datanode_uuid',
            'ip_address',
            'snmp_port',
        ])->where('agent_uuid = ?', $uuid->getBytes());
        return $db->fetchRow($query);
    }

    public function getCredentialUuid(string $targetIdentifier): UuidInterface
    {
        return Uuid::fromBytes($this->requireTarget($targetIdentifier)->credential_uuid);
    }

    public function getDestination(string $targetIdentifier): string
    {
        return inet_ntop($this->requireTarget($targetIdentifier)->ip_address);// TODO: Add port
    }

    public function getRemoteSnmpClient(string $targetIdentifier): RemoteSnmpClient
    {
        $datanodeUuid = Uuid::fromBytes($this->requireTarget($targetIdentifier)->datanode_uuid);
        return (new RemoteSnmpClient())->withTarget($datanodeUuid->toString());
    }
}
