<?php

namespace Icinga\Module\Imedge\Graphing;

use gipfl\ZfDb\Adapter\Pdo\PdoAdapter;
use Icinga\Web\UrlParams;
use IMEdge\RrdGraph\GraphDefinition;
use IMEdge\Web\Grapher\Graph\ImedgeRrdGraph;
use IMEdge\Web\Grapher\GraphModifier\Modifier;
use IMEdge\Web\Grapher\GraphRendering\RrdImage;
use IMEdge\Web\Grapher\GraphTemplateLoader;
use IMEdge\Web\Grapher\Structure\ExtendedRrdInfo;
use IMEdge\Web\Rpc\IMEdgeClient;
use Ramsey\Uuid\UuidInterface;
use RuntimeException;

class RrdImageLoader
{
    public RrdFileInfoLoader $infos;
    protected GraphTemplateLoader $templates;
    /** @var array<string, array<string, ExtendedRrdInfo>> */
    protected array $instancesCache = [];
    /** @var array<string, GraphDefinition> */
    protected array $templatesCache = [];
    /** @var array<string, IMEdgeClient> */
    protected array $imEdgeClients = [];

    public function __construct(PdoAdapter $db)
    {
        $this->infos = new RrdFileInfoLoader($db);
        $this->templates = new GraphTemplateLoader();
    }

    public function getSingleImageForUrlParams(UrlParams $params, ?ImedgeRrdGraph $graph = null): ?RrdImage
    {
        $metricFilesUuids = explode(',', $params->get('metricFiles'));
        if (count($metricFilesUuids) !== 1) {
            throw new \InvalidArgumentException(sprintf('Single file expected, got %d', count($metricFilesUuids)));
        }
        $template = $params->getRequired('template');
        $infos = [];
        foreach ($metricFilesUuids as $fileUuid) {
            $infos[$fileUuid->getBytes()] = $this->infos->load($fileUuid);
        }
        $graph ??= new ImedgeRrdGraph();
        $def = $this->getTemplateDefinition($template);
        $graph->setDefinition(Modifier::withFile($def, $infos[0]->getFilename()));

        return new RrdImage($graph, $template, $this->getClientForRrdInfo($infos[0]), [$infos[0]]);
    }

    public function getTemplateDefinition(string $template): GraphDefinition
    {
        return $this->templatesCache[$template] ??= $this->templates->loadDefinition($template);
    }

    protected function getClientForStoreIdentifier(string $identifier): IMEdgeClient
    {
        return $this->imEdgeClients[$identifier] ??= (new IMEdgeClient())->withTarget($identifier);
    }

    protected function getClientForRrdInfo(ExtendedRrdInfo $info): IMEdgeClient
    {
        return $this->getClientForStoreIdentifier($info->getMetricStoreIdentifier());
    }

    /**
     * @param ExtendedRrdInfo[] $infos
     */
    protected function getClientForFiles(array $infos): IMEdgeClient
    {
        $id = null;
        foreach ($infos as $info) {
            if ($id === null) {
                $id = $info->getMetricStoreIdentifier();
            } else {
                if ($id !== $info->getMetricStoreIdentifier()) {
                    throw new \InvalidArgumentException(
                        'Cannot combine measurements from multiple stores in a single graph'
                    );
                }
            }
        }
        if ($id === null) {
            throw new RuntimeException('Cannot find a metric store for no graph');
        }

        return $this->getClientForStoreIdentifier($id);
    }

    /**
     * @return ExtendedRrdInfo[]
     */
    public function getInstances(UuidInterface $deviceUuid, string $measurementName): array
    {
        $key = $deviceUuid->getBytes();
        $this->instancesCache[$key] ??= [];
        if (!isset($this->instancesCache[$key][$measurementName])) {
            $instances = [];
            foreach ($this->infos->loadDeviceMeasurementInstances($deviceUuid, $measurementName) as $info) {
                $instances[$info->getCi()->getInstance()] = $info;
            }

            $this->instancesCache[$key][$measurementName] = $instances;
        }

        return $this->instancesCache[$key][$measurementName];
    }

    public function getDeviceImg(
        UuidInterface $deviceUuid,
        string $measurementName,
        string $instance,
        string $template
    ): ?RrdImage {
        $instances = $this->getInstances($deviceUuid, $measurementName);
        if (!isset($instances[$instance])) {
            return null;
        }
        $info = $instances[$instance];
        $graph = new ImedgeRrdGraph();
        $def = $this->getTemplateDefinition($template);
        $graph->setDefinition(Modifier::withFile($def, $info->getFilename()));

        return new RrdImage($graph, $template, $this->getClientForRrdInfo($info), [$info]);
    }

    public function getFileImg(
        UuidInterface $fileUuid,
        string $template
    ): RrdImage {
        $info = $this->infos->load($fileUuid);
        $graph = new ImedgeRrdGraph();
        $def = $this->getTemplateDefinition($template);
        $graph->setDefinition(Modifier::withFile($def, $info->getFilename()));

        return new RrdImage($graph, $template, $this->getClientForRrdInfo($info), [$info]);
    }

    public function getFileDefinition(
        UuidInterface $fileUuid,
        string $template
    ): RrdImage {
        $info = $this->infos->load($fileUuid);
        $def = $this->getTemplateDefinition($template);
        $graph = new ImedgeRrdGraph();
        $graph->setDefinition(Modifier::withFile($def, $info->getFilename()));

        return new RrdImage($graph, $template, $this->getClientForRrdInfo($info), [$info]);
    }
}
