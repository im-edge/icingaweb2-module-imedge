<?php

namespace Icinga\Module\Imedge\Controllers;

use gipfl\IcingaWeb2\CompatController;
use Icinga\Exception\NotFoundError;
use Icinga\Module\Imedge\Graphing\RrdDataExporter;
use IMEdge\RrdGraph\Data\Assignment;
use IMEdge\RrdGraph\Data\DataCalculation;
use IMEdge\RrdGraph\Data\DataDefinition;
use IMEdge\RrdGraph\Data\VariableName;
use IMEdge\RrdGraph\DataType\StringType;
use IMEdge\RrdGraph\GraphDefinition;
use IMEdge\RrdGraph\Rpn\Multiply;
use IMEdge\Web\Rpc\IMEdgeClient;
use Ramsey\Uuid\Uuid;
use RuntimeException;

use function Clue\React\Block\await;

class GrafanaController extends CompatController
{
    use DbTrait;
    use RestApiMethods;

    protected $requiresAuthentication = false;

    /**
     * @throws NotFoundError
     */
    public function init()
    {
        $this->assertApiRequest();
        $this->checkBearerToken('imedge/grafana');
    }

    public function devicesAction()
    {
        $this->runForApi(fn () => $this->sendDevices());
    }

    protected function sendDevices()
    {
        $list = array_map(function ($row) {
            $row->uuid = Uuid::fromBytes($row->uuid)->toString();
            return $row;
        }, $this->db()->fetchAll(
            $this->db()->select()
                ->from(['si' => 'snmp_system_info'], ['uuid', 'system_name' => 'COALESCE(sa.label, si.system_name)'])
                ->join(['sa'=> 'snmp_agent'], 'sa.agent_uuid = si.uuid', [])
                ->order('system_name')
        ));
        $this->sendJsonResponse($list);
    }

    public function snmpInterfacesAction()
    {
        $this->runForApi(fn () => $this->sendInterfaces());
    }

    protected function sendInterfaces()
    {
        $device = $this->params->get('device');
        $list = [];
        if ($device !== '') {
            $device = Uuid::fromString($device);
            $list = $this->db()->fetchAll(
                $this->db()->select()
                    ->from('snmp_interface_config', ['if_index', 'if_name', 'if_alias', 'if_description'])
                    ->where('system_uuid = ?', $device->getBytes())
                    ->order('if_index')
            );
            if (empty($list)) {
                $list = [];
            }
        }
        $this->sendJsonResponse($list);
    }

    public function metricsAction()
    {
        $this->runForApi(fn () => $this->sendMetrics());
    }

    protected function sendMetrics()
    {
        $device = $this->params->get('device');
        if ($device === '') {
            $this->sendJsonResponse([]);
            return;
        }

        $device = Uuid::fromString($device);
        $ifIndex = $this->params->get('ifIndex');
        if ($ifIndex === '') {
            $this->sendJsonResponse([]);
            return;
        }
        $metric = $this->params->getRequired('metric');
        $datasources = $this->db()->fetchAll(
            $this->db()->select()
                ->from(['rf' => 'rrd_file'], ['filename', 'metric_store_uuid'])
                ->join(
                    ['ds' => 'rrd_datasource'],
                    'rf.rrd_datasource_list_checksum = ds.datasource_list_uuid',
                    ['ds.datasource_name']
                )
                ->where('device_uuid = ?', $device->getBytes())
                ->where('instance = ?', $ifIndex)
                ->where('measurement_name = ?', $metric)
        );
        $defs = new GraphDefinition();
        $exports = [];
        $aggregations = [
            'Min' => 'MIN',
            'Avg' => 'AVERAGE',
            'Max' => 'MAX'
        ];
        $metricStore = null;
        $hasZero = false;
        foreach ($datasources as $row) {
            $filename = new StringType($row->filename);
            $ds = new StringType($row->datasource_name);
            if ($metricStore === null) {
                $metricStore = Uuid::fromBytes($row->metric_store_uuid)->toString();
            } elseif ($metricStore !== Uuid::fromBytes($row->metric_store_uuid)->toString()) {
                throw new RuntimeException('Cannot yet combine metrics from multiple stores'); // Hint: is doable
            }
            foreach ($aggregations as $suffix => $aggregation) {
                $varName = $row->datasource_name . $suffix;
                $exports[$varName] = $varName;
                $defs->addAssignment(new Assignment(
                    Assignment::TAG_DATA_DEFINITION,
                    new VariableName($varName),
                    new DataDefinition($filename, $ds, new StringType($aggregation))
                ));
                if (!$hasZero) {
                    $defs->addAssignment(new Assignment(
                        Assignment::TAG_DATA_CALCULATION,
                        new VariableName('alwaysZero'),
                        new DataCalculation(new Multiply(), [0, $varName])
                    ));
                    $exports['alwaysZero'] = 'alwaysZero';
                    $hasZero = true;
                }
                if (str_contains($row->datasource_name, 'Octets')) {
                    $dsBits = str_replace('Octets', 'Bits', $row->datasource_name);
                    $dsBitsVarName = $dsBits . $suffix;
                    $exports[$dsBitsVarName] = $dsBitsVarName;
                    $defs->addAssignment(new Assignment(
                        Assignment::TAG_DATA_CALCULATION,
                        new VariableName($dsBitsVarName),
                        new DataCalculation(new Multiply(), [8, $varName])
                    ));
                }
            }
        }
        if ($metricStore === null) {
            $this->sendJsonResponse([]);
            return;
        }

        $command = RrdDataExporter::prepareExportCommand(
            $defs,
            $exports,
            $this->params->get('start', time() - 86400),
            $this->params->get('end', time()),
            1500
        );
        $client = (new IMEdgeClient())->withTarget($metricStore);
        $result = await($client->request('rrd.data', [$command]));
        $columns = $result->meta->legend;
        $time = $result->meta->start;
        $step = $result->meta->step;

        $final = [];
        foreach ($result->data as $row) {
            $row = ['timestamp' => $time] + array_combine($columns, $row);
            $time += $step;
            $final[] = (object) $row;
        }
        $this->sendJsonResponse($final);
    }
}
