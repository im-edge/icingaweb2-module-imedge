<?php

namespace Icinga\Module\Imedge\Graphing;

use gipfl\Json\JsonString;
use gipfl\ZfDb\Adapter\Pdo\PdoAdapter;
use gipfl\ZfDb\Adapter\Pdo\Pgsql;
use gipfl\ZfDb\Expr;
use IMEdge\RrdStructure\Ds;
use IMEdge\RrdStructure\DsList;
use IMEdge\RrdStructure\RraSet;
use IMEdge\RrdStructure\RrdInfo;
use IMEdge\Web\Grapher\Structure\Ci;
use IMEdge\Web\Grapher\Structure\ExtendedRrdInfo;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

class RrdFileInfoLoader
{
    protected const NS_RRD_DEFINITION = '2e012390-58f9-4e84-8d15-ac61fec61ff1';
    protected PdoAdapter $db;
    protected array $rraSetCache = [];
    protected array $dsListCache = [];
    protected UuidInterface $nsRrdDefinition;

    public function __construct(PdoAdapter $db)
    {
        $this->db = $db;
        $this->nsRrdDefinition = Uuid::fromString(self::NS_RRD_DEFINITION);
    }

    protected function fetchRraSet(string $binaryChecksum): RraSet
    {
        if (! isset($this->rraSetCache[$binaryChecksum])) {
            $rows = $this->fetchAll('rrd_archive', 'rrd_archive_set_uuid', $binaryChecksum, 'rra_index');
            $rraList = [];
            foreach ($rows as $row) {
                $rraList[] = $row->definition; // raw RRA definition
            }

            $set = new RraSet($rraList);
            $this->rraSetCache[$binaryChecksum] = $set;
        }

        return $this->rraSetCache[$binaryChecksum];
    }

    protected function fetchDsList(string $binaryChecksum): DsList
    {
        if (! isset($this->dsListCache[$binaryChecksum])) {
            $dsRows = $this->fetchAll('rrd_datasource', 'datasource_list_uuid', $binaryChecksum, 'datasource_index');
            $dsList = new DsList();
            foreach ($dsRows as $row) {
                $dsList->add(new Ds(
                    $row->datasource_name,
                    $row->datasource_type,
                    $row->minimal_heartbeat,
                    $row->min_value,
                    $row->max_value
                ));
            }

            $this->dsListCache[$binaryChecksum] = $dsList;
        }

        return $this->dsListCache[$binaryChecksum];
    }

    /**
     * @return ExtendedRrdInfo[]
     */
    public function loadDeviceMeasurementInstances(UuidInterface $deviceUuid, string $measurementName): array
    {
        return $this->prepareInfoForFileRows(
            $this->fetchRrdFilesBySystemUuidAndMeasurementName($deviceUuid, $measurementName)
        );
    }

    protected function fetchRrdFilesBySystemUuidAndMeasurementName(
        UuidInterface $deviceUuid,
        string $measurementName
    ): array {
        $db = $this->db;
        return $db->fetchAll($db->select()->from('rrd_file')
            ->where('device_uuid = ?', $deviceUuid->getBytes())
            ->where('measurement_name = ?', $measurementName));
    }

    public function load(UuidInterface $fileUuid): ExtendedRrdInfo
    {
        $row = $this->fetchRowByUuid('rrd_file', $fileUuid);
        if ($row) {
            return $this->prepareInfoForFileRows([$row])[$fileUuid->getBytes()];
        }

        throw new \RuntimeException('Got no RRD file with UUID=' . $fileUuid->toString());
    }

    public function loadByName(string $name): ExtendedRrdInfo
    {
        $row = $this->fetchRrdFileByFilename($name);
        if ($row) {
            return current($this->prepareInfoForFileRows([$row]));
        }

        throw new \RuntimeException('Got no RRD file with filename=' . $name);
    }

    /**
     * @return ExtendedRrdInfo[]
     */
    public function loadCiInstances(Ci $ci): array
    {
        return $this->prepareInfoForFileRows(
            $this->fetchRrdFilesByCi($ci)
        );
    }

    protected function fetchRrdFilesByCi(Ci $ci): array
    {
        $db = $this->db;
        $query = $db->select()->from('rrd_file')
            ->where('device_uuid = ?', Uuid::uuid5($this->nsRrdDefinition, $ci->getHostname())->getBytes());

        if ($service = $ci->getSubject()) {
            $query->where('measurement_name = ?', $service);
        } else {
            $query->where('measurement_name IS NULL');
        }

        return $db->fetchAll($query);
    }

    /**
     * @param array $rows
     * @return ExtendedRrdInfo[]
     */
    public function prepareInfoForFileRows(array $rows): array
    {
        /** @var RrdInfo[] $result */
        $result = [];
        foreach ($rows as $row) {
            $uuid = Uuid::fromBytes($row->uuid);
            $ci = new Ci(
                Uuid::fromBytes($row->device_uuid)->toString(),
                $row->measurement_name,
                $row->instance,
                $row->tags ? JsonString::decode($row->tags) : null
            );
            $info = new ExtendedRrdInfo(
                $uuid,
                $row->filename,
                $row->rrd_step,
                $this->fetchDsList($row->rrd_datasource_list_checksum),
                $this->fetchRraSet($row->rrd_archive_set_checksum),
                $ci,
                Uuid::fromBytes($row->metric_store_uuid)->toString()
            );
            $info->setHeaderSize($row->rrd_header_size ?: 0); // TODO: We need to fill this!!
            $result[$row->uuid] = $info;
        }

        return $result;
    }

    protected function fetchRowByUuid($table, UuidInterface $uuid, $uuidColumn = 'uuid')
    {
        $db = $this->db;
        return $db->fetchRow($db->select()->from($table)->where("$uuidColumn = ?", $this->hexUuid($uuid)));
    }

    protected function fetchRrdFileByFilename($name)
    {
        $db = $this->db;
        return $db->fetchRow($db->select()->from('rrd_file')->where('filename = ?', $name));
    }

    protected function fetchAll($table, $keyColumn, $keyValue, $order = null): array
    {
        $db = $this->db;
        if (is_array($keyValue) && count($keyValue) === 1) {
            $keyValue = $keyValue[0];
        }
        $query = $db->select()->from($table);
        if (is_array($keyValue)) {
            foreach ($keyValue as &$value) {
                $value = $this->hexLiteral($value);
            }
            $query->where("$keyColumn IN (?)", $keyValue);
        } else {
            $query->where("$keyColumn = ?", $this->hexLiteral($keyValue));
        }
        if ($order !== null) {
            $query->order($order);
        }

        return $db->fetchAll($query);
    }

    protected function hexUuid(UuidInterface $uuid): Expr
    {
        return $this->hexLiteral($uuid->getBytes());
    }

    protected function hexLiteral(string $binary): Expr
    {
        if ($this->db instanceof Pgsql) {
            return new Expr("'\\x" . bin2hex($binary) . "'");
        }

        return new Expr('0x' . bin2hex($binary));
    }
}
