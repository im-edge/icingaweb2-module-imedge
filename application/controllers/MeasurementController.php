<?php

namespace Icinga\Module\Imedge\Controllers;

use gipfl\IcingaWeb2\CompatController;
use gipfl\IcingaWeb2\Link;
use gipfl\IcingaWeb2\Widget\Tabs;
use gipfl\Web\Widget\Hint;
use Icinga\Module\Imedge\Graphing\RrdFileInfoLoader;
use Icinga\Module\Imedge\Web\Table\Measurement\DsInfoTable;
use Icinga\Module\Imedge\Web\Table\Measurement\FileInfoTable;
use Icinga\Module\Imedge\Web\Table\Measurement\RraInfoByDurationTable;
use Icinga\Module\Imedge\Web\Table\Measurement\RraInfoTable;
use Icinga\Module\Imedge\Web\Widget\Measurement\MeasurementFileActions;
use IMEdge\Web\Grapher\Structure\ExtendedRrdInfo;
use IMEdge\Web\Rpc\IMEdgeClient;
use ipl\Html\Html;
use Icinga\Application\Benchmark;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

use function Clue\React\Block\await;

class MeasurementController extends CompatController
{
    use DbTrait;

    protected ?ExtendedRrdInfo $info = null;
    protected ?array $pending = null;

    public function init()
    {
        $info = $this->requireInfo();
        $client = (new IMEdgeClient())->withTarget($info->getMetricStoreIdentifier());
        $filename = $info->getFilename();
        if ($this->getRequest()->getActionName() !== 'forgotten') {
            $actions = new MeasurementFileActions($this->url(), $filename);
            if ($url = $actions->eventuallyRun($client)) {
                $this->redirectNow($url);
            }
            if ($this->getRequest()->getActionName() !== 'graph') {
                $this->actions()->add($actions);
            }
            try {
                $this->pending = await($client->request('rrd.pending', [
                    'file' => $filename
                ]));
            } catch (\Throwable $e) {
                $this->content()->add(Hint::warning($e->getMessage()));
            }
        }

        if ($this->getRequest()->getActionName() !== 'graph') {
            $this->addTitle('RRD File: %s', $filename);
        }
        $this->fileTabs($info->getUuid());
    }

    public function graphAction()
    {
        $info = $this->requireInfo();
        $ci = $info->getCi();
        $this->addTitle(implode(' - ', array_filter([
            $ci->getHostname(),
            $ci->getSubject(),
            $ci->getInstance()
        ], function ($value) {
            return $value !== null;
        })));
        return;
        /*
        $this->content()->add([
            (new RrdFileRenderer($info, 640, 320))
                ->setRemoteClient((new IMEdgeClient())->withTarget($info->getMetricStoreIdentifier()))
                ->showActionsFor($this->url())
                ->setEnd($end = $this->params->get('metricEnd', floor(time() / 60) * 60))
                ->setStart($this->params->get('metricStart', $end - 86400 * 7))
        ]);
        */
    }

    public function fileAction()
    {
        $info = $this->requireInfo();
        $client = (new IMEdgeClient())->withTarget($info->getMetricStoreIdentifier());
        $filename = $info->getFilename();
        $rp = ['file' => $filename];
        Benchmark::measure('Fetching essential data');
        $last = $this->optionallyGetLast($rp, $client);
        $first = await($client->request('rrd.first', $rp + ['rra' => 0]));
        $oldest = await($client->request('rrd.first', [
            'file' => $filename,
            'rra'  => $info->getRraSet()->getIndexForLongestRra()
        ]));
        Benchmark::measure('Got basic infos');
        $this->content()->add([
            new FileInfoTable($info, $first, $oldest, $last),
        ]);
    }

    public function datasourcesAction()
    {
        $info = $this->requireInfo();
        $client = (new IMEdgeClient())->withTarget($info->getMetricStoreIdentifier());
        $filename = $info->getFilename();
        $rp = ['file' => $filename];
        $last = $this->optionallyGetLast($rp, $client);
        $oldest = await($client->request('rrd.first', [
            'file' => $filename,
            'rra'  => $info->getRraSet()->getIndexForLongestRra()
        ]));
        $summary = $this->getSummary($info, $oldest, $last);
        // $summary = null;
        $this->content()->add([
            Html::tag('h2', $this->translate('Data Sources (DS)')),
            new DsInfoTable($info, $summary, $oldest, $last),
        ]);
    }

    public function labAction()
    {
        // $info = $this->requireInfo();
        // $filename = $info->getFilename();
        $this->addSingleTab('Lab');
        $this->addTitle('LAB');
    }

    public function archivesAction()
    {
        $info = $this->requireInfo();
        $this->content()->add([
            Html::tag('h2', $this->translate('Round Robin Archives (RRA)')),
            new RraInfoTable($info),
            Html::tag('h2', $this->translate('Archives by Duration')),
            new RraInfoByDurationTable($info),
        ]);
    }

    public function pendingAction()
    {
        $pending = $this->pending;
        $this->setAutorefreshInterval(10);
        $this->content()->add(Html::tag('h2', 'Pending'));
        if ($pending === null) {
            $this->content()->add('No updates are pending for this file');
        } elseif (empty($pending)) {
            $this->content()->add('No updates are pending for this file');
        } else {
            $this->content()->add(sprintf('%d pending update(s)', \count($pending)));
            $this->content()->add(Html::tag('pre', [
                'style' => 'max-height: 30em;'
            ], \is_array($pending) ? \implode("\n", array_reverse($pending)) : \var_export($pending, 1)));
        }
    }

    public function forgottenAction()
    {
        $filename = $this->requireInfo()->getFilename();
        $this->content()->add(Html::tag('p', [
            'class' => 'information'
        ], Html::sprintf(
            '%s has been removed from RRDCacheD. You can go %s to the file,'
            . ' it will then once again be loaded into RRDCacheD',
            $filename,
            Link::create(
                'back',
                'measurement/file',
                ['filename' => $filename]
            )
        )));
    }

    public function deletedAction()
    {
        $filename = $this->requireInfo()->getFilename();
        $this->content()->add(Html::tag('p', [
            'class' => 'information'
        ], Html::sprintf(
            '%s has been deleted and removed from RRDCacheD',
            $filename
        )));
    }

    protected function requireInfo(): ExtendedRrdInfo
    {
        if ($this->info === null) {
            if ($filename = $this->params->get('filename')) {
                $this->info = $this->getFileInfoForFilename($filename);
            } else {
                $this->info = $this->getFileInfoForUuid(Uuid::fromString($this->params->getRequired('uuid')));
            }
        }

        return $this->info;
    }

    protected function getFileInfoForUuid(UuidInterface $uuid): ExtendedRrdInfo
    {
        $loader = new RrdFileInfoLoader($this->db());

        return $loader->load($uuid);
    }

    protected function getFileInfoForFilename(string $name): ExtendedRrdInfo
    {
        $loader = new RrdFileInfoLoader($this->db());
        return $loader->loadByName($name);
    }

    protected function getSummary(ExtendedRrdInfo $fileInfo, $start, $end)
    {
        $files = [$fileInfo->getFilename()];
        $dsNames = $fileInfo->listDsNames();
        $imedge = (new IMEdgeClient())->withTarget($fileInfo->getMetricStoreIdentifier());

        return await($imedge->request('rrd.calculate', [
            'files' => $files,
            'dsNames' => $dsNames,
            'start' => $start,
            'end'   => $end,
        ]));
    }

    protected function optionallyGetLast($rp, IMEdgeClient $client)
    {
        try {
            $last = await($client->request('rrd.last', $rp));
        } catch (\Exception $e) {
            $this->content()->add(Html::tag('p', ['class' => 'error'], 'LAST failed: ' . $e->getMessage()));
            $last = null;
        }

        return $last;
    }

    protected function fileTabs(UuidInterface $uuid): Tabs
    {
        if ($this->pending === null) {
            $pendingTitle = $this->translate('Pending');
        } else {
            $pendingTitle = sprintf(
                $this->translate('Pending (%d)'),
                count($this->pending)
            );
        }
        $params = [
            'uuid' => $uuid->toString()
        ];
        $tabs = $this->tabs()->add('graph', [
            'label'     => $this->translate('Graph'),
            'url'       => 'imedge/measurement/graph',
            'urlParams' => $params,
        ])->add('file', [
            'label'     => $this->translate('File Info'),
            'url'       => 'imedge/measurement/file',
            'urlParams' => $params,
        ])->add('datasources', [
            'label'     => $this->translate('Data Sources'),
            'url'       => 'imedge/measurement/datasources',
            'urlParams' => $params,
        ])->add('archives', [
            'label'     => $this->translate('Archives'),
            'url'       => 'imedge/measurement/archives',
            'urlParams' => $params,
        ])->add('pending', [
            'label'     => $pendingTitle,
            'url'       => 'imedge/measurement/pending',
            'urlParams' => $params,
        ]);
        $action = $this->getRequest()->getActionName();
        if ($action === 'forgotten' || $action === 'deleted') {
            $action = 'file';
        }
        $tabs->activate($action);

        return $tabs;
    }
}
