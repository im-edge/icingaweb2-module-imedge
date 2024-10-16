<?php

namespace Icinga\Module\Imedge\Controllers;

use gipfl\IcingaWeb2\CompatController;
use Icinga\Module\Imedge\Web\Form\Filter\NodeFilterForm;
use Icinga\Module\Imedge\Web\Table\Node\NodeDbStreamTable;
use IMEdge\Web\Rpc\IMEdgeClient;
use Ramsey\Uuid\Uuid;

class HistoryController extends CompatController
{
    use DbTrait;

    public function tableSyncAction()
    {
        $this->addSingleTab($this->translate('Table Sync'));
        $this->addTitle($this->translate('Datanode Table Sync History'));
        $form = new NodeFilterForm($this->db());
        $form->setAction((string) $this->getOriginalUrl()->without('datanode'));
        $form->handleRequest($this->getServerRequest());
        $this->content()->add($form);
        $client = new IMEdgeClient();
        if ($uuid = $form->getUuid()) {
            $client = $client->withTarget($uuid->toString());
        } else {
            return;
        }

        $table = new NodeDbStreamTable($client, $this->enumNodes());
        $this->content()->add($table);
    }

    //  Duplicate, see SnmpController
    protected function enumNodes(): array
    {
        $db = $this->db();
        $result = [];
        foreach ($db->fetchPairs($db->select()->from('datanode', ['uuid', 'label'])) as $uuid => $label) {
            $result[Uuid::fromBytes($uuid)->toString()] = $label;
        }

        return $result;
    }
}
