<?php

namespace Icinga\Module\Imedge\Web\Widget\Rpc;

use gipfl\Format\LocalDateFormat;
use gipfl\Format\LocalTimeFormat;
use gipfl\Web\Table\NameValueTable;
use gipfl\Web\Widget\Hint;
use gipfl\ZfDb\Adapter\Adapter;
use IMEdge\Web\Data\Lookup\AutonomousSystemLookup;
use IMEdge\Web\Data\Lookup\MacAddressBlockLookup;
use IMEdge\Web\Data\Widget\AutonomousSystem;
use IMEdge\Web\Data\Widget\MacAddress;
use ipl\Html\HtmlDocument;
use ipl\Html\HtmlString;
use ipl\Html\Table;
use ipl\Html\Text;

class LiveSnmpResult extends HtmlDocument
{
    protected string $scenarioName;
    protected $result;
    protected Adapter $db;
    protected AutonomousSystemLookup $asLookup;

    public function __construct(string $scenarioName, $result, Adapter $db)
    {
        $this->scenarioName = $scenarioName;
        $this->result = $result;
        $this->db = $db;
        $this->asLookup = new AutonomousSystemLookup($db);
    }

    protected function assemble()
    {
        $timeFormatter = new LocalTimeFormat();
        $dateFormatter = new LocalDateFormat();
        $macLookup = new MacAddressBlockLookup($this->db);
        if ($this->scenarioName === 'softwareInstalled') {
            $table = new LiveSoftwareTable($this->result->result);
        } elseif (in_array($this->scenarioName, ['sysInfo', 'cdpConfig'])) {
            if ($this->result->success === false) {
                $this->add(Hint::error($this->result->errorMessage));
                return;
            }
            $table = new NameValueTable();
            foreach ($this->result->result as $key => $value) {
                if ($value->value && in_array($key, ['sysUpTime', 'snmpEngineTime', 'hrSystemUptime'])) {
                    if ($value->type === 'time_ticks') {
                        $timestamp = SnmpUptime::getDateTime($value->value / 100)->getTimestamp();
                    } else {
                        $timestamp = SnmpUptime::getDateTime($value->value)->getTimestamp();
                    }
                    $value = $dateFormatter->getFullDay($timestamp)
                        . ', '
                        . $timeFormatter->getTime($timestamp) . " ($value->value)";
                } else {
                    $value = SnmpValue::getReadableSnmpValue($value);
                }
                if ($key === 'sysServices' && is_int($value)) {
                    $value = implode(', ', SysServices::getList($value)) . " ($value)";
                }
                if (is_scalar($value)) {
                    $table->addNameValueRow($key, new HtmlString(
                        nl2br((new Text($value))->render())
                    ));
                } else {
                    $table->addNameValueRow($key, new HtmlString(
                        nl2br((new Text(var_export($value, 1)))->render())
                    ));
                }
            }
        } else {
            $table = new Table();
            $knownHeaders = [];
            foreach ($this->result->result as $row) {
                $tableRow = [];
                foreach ($row as $key => $value) {
                    $knownHeaders[$key] = true;
                    if ($key === 'physicalAddress') { // No, not good :D
                        if (substr($value->value, 0, 2) === '0x') {
                            $bin = hex2bin(substr($value->value, 2));
                        } else {
                            $bin = $value->value;
                        }
                        if ($bin !== '') {
                            $tableRow[] = MacAddress::fromBinary($bin, $macLookup);
                        } else {
                            $tableRow[] = null;
                        }
                    } elseif ($key === 'peerRemoteAs') {
                        $tableRow[] = $value->value ? new AutonomousSystem((int) $value->value, $this->asLookup) : '-';
                    } else {
                        $tableRow[] = is_object($value) ? SnmpValue::getReadableSnmpValue($value) : $value;
                    }
                }
                $table->add($table::row($tableRow));
            }

            $table->getHeader()->add($table::row(array_keys($knownHeaders), null, 'th'));
        }
        $this->add($table);
    }
}
