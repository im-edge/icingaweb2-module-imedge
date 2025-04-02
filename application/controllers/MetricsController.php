<?php

namespace Icinga\Module\Imedge\Controllers;

use gipfl\IcingaWeb2\CompatController;
use gipfl\IcingaWeb2\Link;
use gipfl\IcingaWeb2\Widget\Tabs;
use gipfl\Web\Table\NameValueTable;
use gipfl\Web\Widget\Hint;
use Icinga\Module\Imedge\Auth\Permission;
use Icinga\Module\Imedge\NodeControl\MetricStoreLookup;
use Icinga\Module\Imedge\Web\Form\Measurement\DeleteUnreferencedFilesForm;
use Icinga\Module\Imedge\Web\Table\Measurement\RrdFilesTable;
use Icinga\Util\Format;
use Icinga\Web\Notification;
use IMEdge\Json\JsonString;
use IMEdge\Web\Rpc\IMEdgeClient;
use ipl\Html\Html;
use ipl\Html\Table;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use stdClass;

use function Clue\React\Block\await;

class MetricsController extends CompatController
{
    use DbTrait;
    use TabsTraitImedge;

    public function indexAction()
    {
        $this->assertPermission(Permission::ADMIN);
        $this->imedgeTabs()->activate('storage');
        $this->addTitle($this->translate('IMEdge Metric Stores'));
        $this->content()->add(Hint::info($this->translate(
            'Metric Stores are where collected Metrics / Performance Data is going to be stored. Please make sure'
            . ' to provide fast and persistent Storage for the configured paths.'
        )));

        $sums = $this->fetchSummaries();
        $table = new Table();
        $table->setAttributes([
            'class' => ['common-table', 'table-row-selectable metrics-summary-table'],
            'data-base-target' => '_next',
        ]);
        $table->getHeader()->add(Table::row([
            null,
            $this->translate('Files'),
            $this->translate('Data Sources'),
            $this->translate('Size'),
        ], null, 'th'));
        $this->actions()->add(Link::create($this->translate('Create'), 'imedge/metrics/create-store', null, [
            'class' => 'icon-plus',
            'title' => $this->translate('Create a new Metric Store')
        ]));
        $metricStoreLookup = new MetricStoreLookup($this->db());
        $body = $table->getBody();
        foreach ($sums as $row) {
            $storeUuid = Uuid::fromBytes($row->metric_store_uuid);
            $body->add(Table::row([
                [
                    Link::create(
                        Html::tag('strong', sprintf(
                            '%s@%s',
                            $metricStoreLookup->getMetricStoreName($storeUuid),
                            $metricStoreLookup->getMetricStoreNodeName($storeUuid)
                        )),
                        'imedge/metrics/store',
                        [
                            'store' => $storeUuid->toString()
                        ]
                    ),
                    Html::tag('br'),
                    $metricStoreLookup->getMetricStorePath($storeUuid),
                ],
                $this->showRefUnref($row->cnt_files, $row->cnt_files_lost),
                $this->showRefUnref($row->cnt_datasources, $row->cnt_datasources_lost),
                [
                    Html::tag('strong', ['style' => 'display: block'], Format::bytes($row->size)),
                    $row->size_lost > 0 ? sprintf('(%s unref)', Format::bytes($row->size_lost)) : null,
                ],
            ]));
        }
        $this->content()->add($table);
    }

    protected function showRefUnref($total, $lost)
    {
        return [
            Html::tag('strong', ['style' => 'display: block'], $total),
            // sprintf('%d referenced', $total - $lost),
            // Html::tag('br'),
            $lost > 0 ? sprintf('(%d unref)', $lost) : null,
        ];
    }

    public function storeAction()
    {
        $this->assertPermission(Permission::ADMIN);
        $metricStoreLookup = new MetricStoreLookup($this->db());
        $this->storeTabs()->activate('store');
        $storeUuid = Uuid::fromString($this->params->getRequired('store'));
        $this->addTitle(sprintf(
            $this->translate('Metric Store "%s" on %s'),
            $metricStoreLookup->getMetricStoreName($storeUuid),
            $metricStoreLookup->getMetricStoreNodeName($storeUuid)
        ));
        $summary = $this->fetchSummariesPerStore($storeUuid);
        $this->setAutorefreshInterval(15);
        $cntScheduled = 0;
        if ($summary->cnt_files_lost > 0) {
            $client = (new IMEdgeClient())->withTarget($storeUuid->toString());
            $cntScheduled = count((array) await($client->request('metricStore.getFiledScheduledForDeletion')));
            if ($cntScheduled > 0) {
                $this->setAutorefreshInterval(2);
            }
            $hintContent = [
                sprintf(
                    $this->translate(
                        'This Store has %d unreferenced RRD files with %d DataSources, occupying %s on disk.'
                    ),
                    $summary->cnt_files_lost,
                    $summary->cnt_datasources_lost,
                    Format::bytes($summary->size_lost),
                ),
                Html::tag('br'),
                Html::sprintf(
                    $this->translate('You might want to %s or to %s them'),
                    Link::create($this->translate('delete'), 'imedge/metrics/store', [
                        'action' => 'deleteUnreferenced',
                        'store'  => $storeUuid->toString()
                    ]),
                    Link::create($this->translate('inspect'), 'imedge/metrics/files', [
                        'store' => $storeUuid->toString()
                    ]),
                )
            ];
            if ($this->params->get('action') === 'deleteUnreferenced') {
                $form = new DeleteUnreferencedFilesForm();
                $form->on($form::ON_SUCCESS, function () use ($client, $storeUuid) {
                    $files = $this->fetchFilesForDeletion($storeUuid);
                    if (count($files) > 0) {
                        if (await($client->request('metricStore.scheduleForDeletion', [$files]))) {
                            Notification::success(sprintf(
                                $this->translate('%d files have been scheduled for deletion'),
                                count($files)
                            ));
                        }
                    }
                    $this->redirectNow($this->url()->without('action'));
                });
                $form->handleRequest($this->getServerRequest());
                if ($form->hasBeenCancelled()) {
                    $this->redirectNow($this->url()->without('action'));
                }

                $hintContent[] = $form;
            }
            $this->content()->add(Hint::warning($hintContent));
        } else {
            $this->content()->add(Hint::info(
                $this->translate('All files are still referenced by configured SNMP agents')
            ));
        }
        $fileCount = $summary->cnt_files;
        if ($cntScheduled > 0) {
            $fileCount .= sprintf(' (%s)', sprintf($this->translate('%d scheduled for deletion'), $cntScheduled));
        }
        $table = new NameValueTable();
        $table->addNameValuePairs([
            $this->translate('Node') => $metricStoreLookup->getMetricStoreNodeName($storeUuid),
            $this->translate('Path') => $metricStoreLookup->getMetricStorePath($storeUuid),
            $this->translate('RRD Files on disk') => $fileCount,
            $this->translate('Data Sources') => $summary->cnt_datasources,
            $this->translate('Disk Storage') => Format::bytes($summary->size),
        ]);
        $this->content()->add($table);
    }

    protected function fetchFilesForDeletion(UuidInterface $metricStoreUuid): array
    {
        $query = $this->db()->select()->from(['rf' => 'rrd_file'], [
            'rf.uuid',
            'rf.device_uuid',
            'rf.filename',
            'rf.measurement_name',
            'rf.instance',
            'rf.tags',
        ])->joinLeft(['sa' => 'snmp_agent'], 'rf.device_uuid = sa.agent_uuid', [])
            ->where('rf.metric_store_uuid = ?', $metricStoreUuid->getBytes())
            ->where('rf.device_uuid != ?', $metricStoreUuid->getBytes())
        ->where('sa.agent_uuid IS NULL');
        $rows = $this->db()->fetchAll($query);
        foreach ($rows as $row) {
            $row->uuid = Uuid::fromBytes($row->uuid)->toString();
            $row->device_uuid = Uuid::fromBytes($row->device_uuid)->toString();
            $row->tags = (array) JsonString::decode($row->tags);
        }

        return $rows;
    }

    public function filesAction()
    {
        $this->storeTabs()->activate('files');
        $this->assertPermission(Permission::ADMIN);
        $metricStoreLookup = new MetricStoreLookup($this->db());
        $storeUuid = Uuid::fromString($this->params->getRequired('store'));
        $this->addTitle(sprintf(
            $this->translate('Metric Store: %s'),
            $metricStoreLookup->getMetricStoreName($storeUuid)
        ));

        $table = new RrdFilesTable($this->db());
        $table->getQuery()->where('metric_store_uuid = ?', $storeUuid->getBytes());
        $table->renderTo($this);
    }

    protected function fetchSummaries()
    {
        $query = <<<SQL
SELECT
  metric_store_uuid,
  COUNT(*) AS cnt_files,
  SUM(CASE WHEN sa.agent_uuid IS NULL
    AND per_file.device_uuid != per_file.metric_store_uuid
    -- AND per_file.device_uuid != per_file.datanode_uuid
    THEN 1 ELSE 0 END
  ) AS cnt_files_lost,
  SUM(cnt_datasources) AS cnt_datasources,
  SUM(CASE WHEN sa.agent_uuid IS NULL
    AND per_file.device_uuid != per_file.metric_store_uuid
    -- AND per_file.device_uuid != per_file.datanode_uuid
    THEN cnt_datasources ELSE 0 END) AS cnt_datasources_lost,
  ROUND(SUM(rrd_header_size + rrd_data_size)) AS size,
  ROUND(SUM(CASE WHEN sa.agent_uuid IS NULL
    AND per_file.device_uuid != per_file.metric_store_uuid
    -- AND per_file.device_uuid != per_file.datanode_uuid
   THEN rrd_header_size + rrd_data_size ELSE 0 END)) AS size_lost
  FROM (
    SELECT
      r.datanode_uuid,
      r.metric_store_uuid,
      r.device_uuid,
      r.uuid,
      r.filename,
      COUNT(DISTINCT ds.datasource_index) AS cnt_datasources,
      COUNT(DISTINCT a.rra_index) AS cnt_archives,
      r.rrd_header_size,
      SUM(a.row_count) / COUNT(DISTINCT ds.datasource_index),
      8 * COUNT(DISTINCT ds.datasource_index) * SUM(a.row_count) / COUNT(DISTINCT ds.datasource_index) AS rrd_data_size
    FROM rrd_file r
      JOIN rrd_datasource ds ON r.rrd_datasource_list_checksum = ds.datasource_list_uuid
      JOIN rrd_archive a ON r.rrd_archive_set_checksum = a.rrd_archive_set_uuid
       GROUP BY r.uuid
) per_file LEFT JOIN snmp_agent sa ON per_file.device_uuid = sa.agent_uuid
GROUP BY metric_store_uuid;
SQL;
        return $this->db()->fetchAll($query);
    }

    protected function fetchSummariesPerStore(UuidInterface $uuid): ?stdClass
    {
        $query = sprintf(<<<SQL
SELECT
  COUNT(*) AS cnt_files,
  SUM(CASE WHEN sa.agent_uuid IS NULL
      AND per_file.device_uuid != per_file.metric_store_uuid
      -- AND per_file.device_uuid != per_file.datanode_uuid
      THEN 1 ELSE 0 END) AS cnt_files_lost,
  SUM(cnt_datasources) AS cnt_datasources,
  SUM(CASE WHEN sa.agent_uuid IS NULL
      AND per_file.device_uuid != per_file.metric_store_uuid
      -- AND per_file.device_uuid != per_file.datanode_uuid
      THEN cnt_datasources ELSE 0 END) AS cnt_datasources_lost,
  ROUND(SUM(rrd_header_size + rrd_data_size)) AS size,
  ROUND(SUM(CASE WHEN
    sa.agent_uuid IS NULL
      AND per_file.device_uuid != per_file.metric_store_uuid
      -- AND per_file.device_uuid != per_file.datanode_uuid
   THEN rrd_header_size + rrd_data_size
   ELSE 0
   END)) AS size_lost
  FROM (
    SELECT
      r.datanode_uuid,
      r.metric_store_uuid,
      r.device_uuid,
      r.uuid,
      r.filename,
      COUNT(DISTINCT ds.datasource_index) AS cnt_datasources,
      COUNT(DISTINCT a.rra_index) AS cnt_archives,
      r.rrd_header_size,
      SUM(a.row_count) / COUNT(DISTINCT ds.datasource_index),
      8 * COUNT(DISTINCT ds.datasource_index) * SUM(a.row_count) / COUNT(DISTINCT ds.datasource_index) AS rrd_data_size
    FROM rrd_file r
      JOIN rrd_datasource ds ON r.rrd_datasource_list_checksum = ds.datasource_list_uuid
      JOIN rrd_archive a ON r.rrd_archive_set_checksum = a.rrd_archive_set_uuid
   WHERE metric_store_uuid = %s
       GROUP BY r.uuid
) per_file LEFT JOIN snmp_agent sa ON per_file.device_uuid = sa.agent_uuid;
SQL, '0x' . $uuid->getHex());
        // pgsql: "'\\x" . $uuid->getHex()) . "'"
        $result = $this->db()->fetchRow($query);
        if ($result) {
            return $result;
        }

        return null;
    }

    protected function storeTabs(): Tabs
    {
        $urlParams = [
            'store' => $this->params->getRequired('store'),
        ];

        return $this->tabs()->add('store', [
            'label' => $this->translate('Metrics: Storage'),
            'url'   => 'imedge/metrics/store',
            'urlParams' => $urlParams,
        ])->add('files', [
            'label' => $this->translate('Files'),
            'url' => 'imedge/metrics/files',
            'urlParams' => $urlParams,
        ]);
    }
}
