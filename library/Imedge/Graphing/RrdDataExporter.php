<?php

namespace Icinga\Module\Imedge\Graphing;

use IMEdge\RrdGraph\Data\VariableName;
use IMEdge\RrdGraph\DataType\StringType;
use IMEdge\RrdGraph\GraphDefinition;

class RrdDataExporter
{
    public static function prepareExportCommand(
        GraphDefinition $definition,
        array $columns,
        int $start,
        int $end,
        int $width
    ): string {
        $exportDef = new GraphDefinition();
        foreach ($definition->getSortedAssignments() as $def) {
            $exportDef->addAssignment($def);
        }
        $command = (string) $exportDef;
        $command = "xport --start $start -m $width --end $end --json " . $command;
        foreach ($columns as $varName => $label) {
            $varName = new VariableName($varName);
            $command .= " XPORT:$varName:" . (new StringType($label));
        }

        return $command;
    }
}
