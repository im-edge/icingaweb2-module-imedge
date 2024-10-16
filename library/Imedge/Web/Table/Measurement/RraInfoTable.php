<?php

namespace Icinga\Module\Imedge\Web\Table\Measurement;

use gipfl\Translation\TranslationHelper;
use Icinga\Util\Format;
use IMEdge\RrdStructure\RraAggregation;
use IMEdge\RrdStructure\RraForecasting;
use IMEdge\RrdStructure\RrdInfo;
use IMEdge\Web\Grapher\Util\RrdFormat;
use ipl\Html\Table;

class RraInfoTable extends Table
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
        $dsCount = $info->countDataSources();
        $this->getHeader()->add(Table::row([
            $this->translate('Index'),
            $this->translate('Consolidation Function'),
            $this->translate('Rows'),
            $this->translate('Current Row'),
            $this->translate('PDPs per row'),
            $this->translate('XFF'),
            $this->translate('Size'),
        ], null, 'th'));
        foreach ($rraSet->getRras() as $idx => $rra) {
            $size = sprintf(
                '%s * %d = %s',
                Format::bytes($rra->getDataSize()),
                $dsCount,
                Format::bytes($rra->getDataSize() * $dsCount)
            );
            if ($rra instanceof RraAggregation) {
                $duration = sprintf(
                    '%d * %s = %s',
                    $rra->getRows(),
                    RrdFormat::seconds($rra->getSteps() * $info->getStep()),
                    RrdFormat::seconds($rra->getRows() * $rra->getSteps() * $info->getStep())
                );
                $row = [
                    $idx,
                    $rra->getConsolidationFunction(),
                    $duration,
                    $rra->getCurrentRow(),
                    $rra->getSteps(),
                    RrdFormat::percent($rra->getXFilesFactor()),
                    $size,
                ];
            } elseif ($rra instanceof RraForecasting) {
                $row = [
                    $idx,
                    $rra->getConsolidationFunction(),
                    null,
                    $rra->getCurrentRow(),
                    null,
                    null,
                    $size,
                ];
            } else {
                throw new \RuntimeException('RRA must be either Aggreation or Forecasting');
            }
            $this->add(Table::row($row));
        }
    }
}
