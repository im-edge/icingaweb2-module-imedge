<?php

namespace Icinga\Module\Imedge\Web\Widget\Rpc;

use gipfl\Translation\TranslationHelper;
use Icinga\Module\Imedge\Snmp\ResultHelper;
use Icinga\Module\Imedge\Snmp\VarBind;
use ipl\Html\Table;

class WalkResultTable extends Table
{
    use TranslationHelper;

    /*
     * success => true,
     * source => 192.0.10.1:123213
     * result =>
     *   counters =>
     *     results => 3
     *     varBinds => 140
     *   nonRepeaters =>
     *     ..
     *   repeaters =>
     *     1.3.6.1.2.1.15.3.1.1 =>
     *       103.143.23.17 =>
     *           0 => 1.3.6.1.2.1.15.3.1.1.103.143.23.17
     *           1 => { type => ip_address, value => "103.143.23.17"
     *  duration => 234323242
     */
    public function __construct(\stdClass $result)
    {
        $flipped = ResultHelper::flipTable($result->repeaters);
        $first = true;
        $columns = [];

        foreach ($flipped as $index => $flippedRow) {
            if ($first) {
                $columns = array_merge([null], array_keys((array) $flippedRow));
                $this->getHeader()->add(
                    $this::row($columns, null, 'th')
                );
                $first = false;
            }
            $row = Table::tr(Table::th($index));
            foreach ($columns as $column) {
                if (isset($flippedRow->$column)) {
                    $td = VarBind::fromSerialization($flippedRow->$column)->value->getReadableValue();
                } else {
                    $td = '-';
                }
                $row->add(Table::td($td));
            }
            $this->getBody()->add($row);
        }
    }
}
