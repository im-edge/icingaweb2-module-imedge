<?php

namespace Icinga\Module\Imedge\Web\Widget\Rpc;

use gipfl\Web\Widget\Hint;
use gipfl\ZfDb\Adapter\Adapter;
use IMEdge\Web\Data\Lookup\AutonomousSystemLookup;
use ipl\Html\HtmlDocument;

class LiveSnmpResult extends HtmlDocument
{
    protected string $scenarioName;
    protected \stdClass $result;
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
        $specialTables = [
            'softwareInstalled' => LiveSoftwareTable::class,
        ];
        if ($this->result->success === false) {
            $this->add(Hint::error($this->result->errorMessage));
            return;
        }
        if (isset($this->result->result->varBinds)) {
            $table = new GetResultTable($this->result->result);
        } elseif (isset($specialTables[$this->scenarioName])) {
            $class = $specialTables[$this->scenarioName];
            $table = new $class($this->result->result);
        } else {
            $table = new WalkResultTable($this->result->result);
        }
        $this->add($table);
    }
}
