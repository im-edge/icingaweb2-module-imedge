<?php

namespace Icinga\Module\Imedge\Web\Table\Measurement;

use gipfl\IcingaWeb2\Widget\NameValueTable;
use gipfl\Translation\TranslationHelper;
use Icinga\Date\DateFormatter;
use Icinga\Util\Format;
use IMEdge\Web\Grapher\Structure\ExtendedRrdInfo;
use IMEdge\Web\Grapher\Util\RrdFormat;

class FileInfoTable extends NameValueTable
{
    use TranslationHelper;

    protected $defaultAttributes = [
        'class' => 'common-table'
    ];

    protected ExtendedRrdInfo $info;
    protected $first;
    protected $oldest;
    protected $last;

    public function __construct(ExtendedRrdInfo $info, $first, $oldest, $last)
    {
        $this->info = $info;
        $this->first = $first;
        $this->oldest = $oldest;
        $this->last = $last;
    }

    protected function assemble()
    {
        $info = $this->info;
        $this->addNameValuePairs([
            $this->translate('Host') => $info->getCi()->getHostname() ?: '-',
            $this->translate('Subject') => $info->getCi()->getSubject() ?: '-',
            $this->translate('Instance') => $info->getCi()->getInstance() ?: '-',
            $this->translate('RRD Version') => $info->getRrdVersion(),
            $this->translate('Step') => $info->getStep(),
            $this->translate('First') => sprintf(
                '%s (%s)',
                DateFormatter::formatDateTime($this->first),
                DateFormatter::formatDateTime($this->oldest)
            ),
            $this->translate('Last') => DateFormatter::formatDateTime($this->last),
            $this->translate('Last update (File)')
            => DateFormatter::formatDateTime($info->getLastUpdate()),
            $this->translate('Header size') => Format::bytes($info->getHeaderSize()),
            $this->translate('Data size') => Format::bytes($info->getDataSize()),
            $this->translate('Size per DS') => Format::bytes($info->getRraSet()->getDataSize()),
            $this->translate('Max Retention') => RrdFormat::seconds($info->getMaxRetention()),
        ]);
    }
}
