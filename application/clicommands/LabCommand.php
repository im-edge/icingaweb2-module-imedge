<?php

namespace Icinga\Module\Imedge\CliCommands;

use Icinga\Cli\Command;
use Icinga\Module\Imedge\Controllers\DbTrait;
use IMEdge\Web\Rpc\IMEdgeClient;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

use function Clue\React\Block\await;

class LabCommand extends Command
{
    use DbTrait;

    public function shipcredentialsAction(): void
    {
        $this->fail('This action no longer exists, calling it is not necessary');
    }

    public function shiptargetsAction(): void
    {
        $this->fail('This action no longer exists, calling it is not necessary');
    }

    public function shipnotargetsAction(): void
    {
        $this->fail('This action no longer exists');
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
