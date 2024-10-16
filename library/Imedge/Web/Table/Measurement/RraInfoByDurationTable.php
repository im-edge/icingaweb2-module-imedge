<?php

namespace Icinga\Module\Imedge\Web\Table\Measurement;

use gipfl\Translation\TranslationHelper;
use Icinga\Util\Format;
use IMEdge\RrdStructure\RraAggregation;
use IMEdge\RrdStructure\RrdInfo;
use IMEdge\Web\Grapher\Util\RrdFormat;
use ipl\Html\Table;

class RraInfoByDurationTable extends Table
{
    use TranslationHelper;

    protected $defaultAttributes = [
        'class' => 'common-table'
    ];

    protected $info;

    public function __construct(RrdInfo $info)
    {
        $this->info = $info;
    }

    public function assemble()
    {
        $info = $this->info;
        $rraSet = $info->getRraSet();
        $grouped = [];
        foreach ($rraSet->getRras() as $idx => $rra) {
            // Here we ignore RraForecasting
            if ($rra instanceof RraAggregation) {
                $key = sprintf('%s-%s', $rra->getRows(), $rra->getSteps());
                $grouped[$key][$idx] = $rra;
            }
        }
        $dsCount = $info->countDataSources();
        $this->getHeader()->add(Table::row([
            $this->translate('Consolidation Functions'),
            $this->translate('Rows'),
            $this->translate('PDPs per row'),
            $this->translate('Size'),
        ], null, 'th'));
        foreach ($grouped as $rras) {
            $size = 0;
            $cfs = [];
            /** @var RraAggregation $rra */
            foreach ($rras as $rra) {
                $cfs[] = $rra->getConsolidationFunction();
                $size += $rra->getDataSize();
            }

            $size = sprintf(
                '%s * %d = %s',
                Format::bytes($size),
                $dsCount,
                Format::bytes($size * $dsCount)
            );
            $duration = sprintf(
                '%d * %s = %s',
                $rra->getRows(),
                RrdFormat::seconds($rra->getSteps() * $info->getStep()),
                RrdFormat::seconds($rra->getRows() * $rra->getSteps() * $info->getStep())
            );
            $row = [
                \implode(', ', $cfs),
                $duration,
                $rra->getSteps(),
                $size,
            ];

            $this->add(Table::row($row));
        }
    }
}
