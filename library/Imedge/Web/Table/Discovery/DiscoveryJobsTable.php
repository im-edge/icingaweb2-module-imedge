<?php

namespace Icinga\Module\Imedge\Web\Table\Discovery;

use gipfl\Format\LocalDateFormat;
use gipfl\Format\LocalTimeFormat;
use gipfl\IcingaWeb2\Icon;
use gipfl\IcingaWeb2\Link;
use gipfl\Translation\TranslationHelper;
use Icinga\Util\Format;
use ipl\Html\Html;
use ipl\Html\Table;
use Ramsey\Uuid\UuidInterface;
use stdClass;

class DiscoveryJobsTable extends Table
{
    use TranslationHelper;

    protected $defaultAttributes = [
        'class' => 'common-table table-row-selectable discovery-jobs-table',
        'data-base-target' => '_next',
    ];

    protected bool $hasRunningJobs = false;
    protected UuidInterface $nodeUuid;
    protected LocalTimeFormat $timeFormatter;

    public function __construct(UuidInterface $nodeUuid, array $jobs)
    {
        $this->nodeUuid = $nodeUuid;
        uasort($jobs, fn ($a, $b) => $a->tsSendStartMs > $b->tsSendStartMs ? -1 : 1);
        $this->getHeader()->add(Table::row([
            $this->translate('Status'),
            $this->translate('Candidates'),
            $this->translate('Sent'),
        ], null, 'th'));
        $body = $this->getBody();
        $this->timeFormatter = new LocalTimeFormat();
        $this->dateFormatter = new LocalDateFormat();
        foreach ($jobs as $udpPort => $job) {
            if ($job->status === 'running' || $job->status === 'pending') {
                $this->hasRunningJobs = true;
            }
            $rowClasses = ['status-' . $job->status];
            $status = [
                $this->getStatusIcon($job->status),
                Html::tag('strong', strtoupper($job->status))
            ];
            $body->add(Table::row([
                [
                    Link::create($status, 'imedge/discovery/job', [
                        'node' => $this->nodeUuid->toString(),
                        'job'  => $udpPort,
                    ]),
                    $this->formatTimeDetails($job),
                ],
                $job->results ?? 'no results',
                $this->formatPacketStats($job, $udpPort),
            ], ['class' => $rowClasses]));
        }
    }

    protected function getStatusIcon(string $status)
    {
        switch ($status) {
            case 'pending':
            case 'running':
                return Icon::create('spinner');
            case 'finished':
                return Icon::create('check');
            case 'failed':
                return Icon::create('warning-empty');
            case 'aborted':
                return Icon::create('attention-circled');
            default:
                throw new \RuntimeException("Invalid status: $status");
        }
    }

    protected function formatPacketStats(stdClass $job, int $udpPort): array
    {
        return [
            sprintf(
                $this->translate('%s packets (%s)'),
                number_format($job->sentPackets, 0, ',', '.'),
                Format::bytes($job->sentPayloadBytes),
            ),
            Html::tag('br'),
            sprintf(
                $this->translate('from UDP port %d'),
                $udpPort
            ),
        ];
    }

    protected function formatTimeDetails(stdClass $job): array
    {
        $tf = $this->timeFormatter;
        return [
            Html::tag('br'),
            Html::sprintf(
                '%s: %s, %s',
                Html::tag('strong', $this->translate('Started')),
                $this->dateFormatter->getFullDay((int) ($job->tsSendStartMs / 1000)),
                $tf->getTime((int) ($job->tsSendStartMs / 1000))
            ),

            Html::tag('br'),
            Html::sprintf(
                '%s: %s',
                Html::tag('strong', $this->translate('Duration')),
                sprintf('%.2Gs', $job->durationSendMs / 1000),
            ),
        ];
    }

    public function hasRunningJobs(): bool
    {
        return $this->hasRunningJobs;
    }
}
