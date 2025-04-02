<?php

namespace Icinga\Module\Imedge\Web\Form\Discovery;

use gipfl\Translation\TranslationHelper;
use gipfl\Web\Widget\Hint;
use Icinga\Module\Imedge\Web\Form\Form;
use Icinga\Web\Notification;
use IMEdge\Web\Rpc\IMEdgeClient;
use Ramsey\Uuid\UuidInterface;

use function Clue\React\Block\await;

class DropDiscoveryResultsForm extends Form
{
    use TranslationHelper;

    protected UuidInterface $nodeUuid;
    public int $jobId;

    public function __construct(UuidInterface $nodeUuid, int $jobId)
    {
        $this->nodeUuid = $nodeUuid;
        $this->jobId = $jobId;
    }

    protected function assemble()
    {
        $this->add(Hint::info(
            $this->translate('This will remove data collected for this scan from the remote IMEdge node')
        ));
        $this->addElement('submit', 'submit', [
            'label' => $this->translate('YES, please remove')
        ]);
    }

    protected function onSuccess()
    {
        $client = (new IMEdgeClient())->withTarget($this->nodeUuid->toString());
        $deleted = await($client->request('snmp.deleteDiscoveryJobResults', [
            $this->jobId
        ]));
        if ($deleted) {
            Notification::success($this->translate('Collected discovery data has been removed'));
        } else {
            Notification::info($this->translate('There was no such Discovery job'));
        }
    }
}
