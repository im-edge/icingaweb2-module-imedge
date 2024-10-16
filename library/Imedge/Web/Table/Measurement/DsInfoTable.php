<?php

namespace Icinga\Module\Imedge\Web\Table\Measurement;

use gipfl\IcingaWeb2\Link;
use gipfl\Translation\TranslationHelper;
use IMEdge\Web\Grapher\Graph\ImedgeRrdGraph;
use IMEdge\Web\Grapher\GraphModifier\Modifier;
use IMEdge\Web\Grapher\GraphRendering\ImedgeGraphPreview;
use IMEdge\Web\Grapher\GraphRendering\RrdImage;
use IMEdge\Web\Grapher\GraphTemplateLoader;
use IMEdge\Web\Grapher\Structure\ExtendedRrdInfo;
use IMEdge\Web\Grapher\Util\RrdFormat;
use IMEdge\Web\Rpc\IMEdgeClient;
use ipl\Html\Table;

class DsInfoTable extends Table
{
    use TranslationHelper;

    protected $defaultAttributes = [
        'class' => 'common-table',
        'data-base-target' => '_next',
    ];

    protected ExtendedRrdInfo $info;
    protected $summary;
    protected $oldest;
    protected $last;

    public function __construct(ExtendedRrdInfo $info, $summary, $oldest, $last)
    {
        $this->info = $info;
        $this->summary = $summary;
        $this->oldest = $oldest;
        $this->last = $last;
    }

    protected function assemble()
    {
        $info = $this->info;
        $summary = $this->summary;
        $filename = $info->getFilename();
        $headers = [$this->translate('Preview')];
        if ($this->summary) {
            $headers[] = $this->translate('MIN / AVG / MAX / STDEV');
        }
        $this->getHeader()->add(Table::row(array_merge($headers, [
            $this->translate('Name'),
            $this->translate('Type'),
            $this->translate('Heartbeat (min)'),
            $this->translate('Limited MIN / MAX'),
        ]), null, 'th'));
        foreach ($info->getDsList()->getDataSources() as $dsInfo) {
            $dsName = $dsInfo->getName();
            if (isset($summary->$filename->$dsName)) {
                $fSummary = $summary->$filename->$dsName;
            } else {
                $fSummary = null;
            }
            $now = \time();
            $columns = [
                [
                    // $this->createDsImage($filename, $dsName, $this->oldest, $this->last, 120, 20),
                    // ' ',
                    $this->createDsImage($filename, $dsName, $now - 4 * 3600, $now, 240, 20),
                ],
            ];
            if ($this->summary) {
                $columns[] = $fSummary ? \sprintf(
                    '%s / %s / %s / %s',
                    RrdFormat::number($fSummary->min),
                    RrdFormat::number($fSummary->avg),
                    RrdFormat::number($fSummary->max),
                    RrdFormat::number($fSummary->stdev)
                ) : '- unavailable -';
            }
            $uuid = $this->info->getCi()->getUuid();
            $this->add(Table::row(array_merge($columns, [
                $uuid ? Link::create($dsName, 'metrics/lab/compose', [
                    'add' => $uuid->toString() . ':' . $dsName,
                ]) : $dsName,
                $dsInfo->getType(),
                $dsInfo->getHeartbeat(),
                \sprintf(
                    '%s / %s',
                    $dsInfo->hasMin() ? $dsInfo->getMin() : '-',
                    $dsInfo->hasMax() ? $dsInfo->getMax() : '-'
                ),
                // last_ds, value, unknown_sec
            ])));
        }
    }

    protected function createDsImage($filename, $dsName, $from, $to, $width, $height): ImedgeGraphPreview
    {
        $graph = new ImedgeRrdGraph();
        $loader = new GraphTemplateLoader();
        $def = $loader->loadDefinition('value');
        $def = Modifier::withFile($def, $filename);
        $def = Modifier::replaceDs($def, 'value', $dsName);
        $graph->setDefinition($def);
        $graph->layout->setOnlyGraph();
        $graph->timeRange->set($from, $to);
        $graph->dimensions->set($width, $height);
        $graph->layout->disableRrdCached();
        $image = new RrdImage($graph, 'default', (new IMEdgeClient())->withTarget($this->info->getMetricStoreIdentifier()));
        $image->loadImmediately();
        $container = new ImedgeGraphPreview($image);
        $container->addAttributes(['style' => sprintf(
            'border-left: 1px solid #ccc; border-bottom: 1px solid #ccc; padding: 0; width: %spx; height: %spx',
            $width,
            $height
        )]);
        return $container;
    }
}
