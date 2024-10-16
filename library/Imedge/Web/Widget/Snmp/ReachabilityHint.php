<?php

namespace Icinga\Module\Imedge\Web\Widget\Snmp;

use gipfl\IcingaWeb2\Link;
use gipfl\Translation\TranslationHelper;
use gipfl\Web\Widget\Hint;
use gipfl\ZfDb\Adapter\Adapter;
use gipfl\ZfDbStore\ZfDbStore;
use IMEdge\Web\Data\Model\DataNode;
use IMEdge\Web\Data\Model\SnmpAgent;
use ipl\Html\Html;
use Ramsey\Uuid\UuidInterface;

class ReachabilityHint extends Hint
{
    use TranslationHelper;

    protected bool $unchecked;

    public function __construct(SnmpAgent $agent, ZfDbStore $dbStore)
    {
        $nodeUuid = $agent->get('datanode_uuid');
        if ($nodeUuid === null) {
            parent::__construct(
                $this->translate('This device has no assigned monitoring node, it will not be scheduled for polling'),
                'warning'
            );
            return;
        }
        $node = DataNode::load($dbStore, $nodeUuid);
        $nodeLink = Link::create(
            $node->get('label'),
            'imedge/node',
            ['uuid' => $node->getUuid()->toString()],
            [
                'data-base-target' => '_main'
            ]
        );
        $this->unchecked = false;

        switch ($this->getTargetHealth($agent->getUuid(), $dbStore->getDb())) {
            case 'reachable':
                $state = 'ok';
                $message = Html::sprintf(
                    $this->translate('This device is reachable and currently being polled by %s'),
                    $nodeLink
                );
                break;
            case 'failing':
                $state = 'error';
                $message = Html::sprintf(
                    $this->translate('This device is currently failing/not reachable by %s'),
                    $nodeLink
                );
                break;
            case 'pending':
                $state = 'info';
                $message = Html::sprintf(
                    $this->translate('This device has been scheduled to be polled by %s'),
                    $nodeLink
                );
                break;
            default:
                $state = 'warning';
                $this->unchecked = true;
                $message = $this->translate('This device has not been scheduled for polling');
        }

        parent::__construct($message, $state);
    }

    public function hasBeenChecked(): bool
    {
        return ! $this->unchecked;
    }

    protected function getTargetHealth(UuidInterface $uuid, Adapter $db): ?string
    {
        $state = $db->fetchOne(
            $db->select()->from('snmp_target_health', 'state')
                ->where('uuid = ?', $uuid->getBytes())
        );
        if ($state) {
            return $state;
        }

        return null;
    }
}
