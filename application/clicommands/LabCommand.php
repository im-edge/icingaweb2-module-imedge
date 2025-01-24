<?php

namespace Icinga\Module\Imedge\CliCommands;

use Icinga\Cli\Command;
use Icinga\Module\Imedge\Controllers\DbTrait;
use Icinga\Module\Imedge\NodeControl\TargetShipper;
use IMEdge\Web\Rpc\IMEdgeClient;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

use function Clue\React\Block\await;

class LabCommand extends Command
{
    use DbTrait;

    public function shipcredentialsAction(): void
    {
        (new TargetShipper($this->db()))->shipCredentials($this->getDataNodeUuid());
    }

    public function shiptargetsAction(): void
    {
        (new TargetShipper($this->db()))->shipTargets($this->getDataNodeUuid());
    }

    public function shipnotargetsAction(): void
    {
        (new TargetShipper($this->db()))->clearTargets($this->getDataNodeUuid());
    }

    public function fetchhealthAction(): void
    {
        $results = await($this->client()->request('snmp.getKnownTargetsHealth', []), $this->loop);
        foreach ($results as $hexUuid => $result) {
            printf("%s: %s\n", $result->target->address->ip, $result->target->state);
        }
    }

    protected function client(): IMEdgeClient
    {
        return (new IMEdgeClient())->withTarget($this->getDataNodeUuid()->toString());
    }

    protected function getDataNodeUuid(): UuidInterface
    {
        $db = $this->db();
        $nodeName = $this->params->getRequired('node');
        return Uuid::fromBytes(
            $db->fetchOne($db->select()->from('datanode', 'uuid')->where('label = ?', $nodeName))
        );
    }
}
