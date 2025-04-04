<?php

namespace Icinga\Module\Imedge\Web\Table\Discovery;

use gipfl\IcingaWeb2\Link;
use gipfl\Translation\TranslationHelper;
use gipfl\ZfDb\Adapter\Pdo\PdoAdapter;
use IMEdge\Web\Data\Lookup\IpToCountryLiteLookup;
use IMEdge\Web\Data\Widget\IpAddress;
use ipl\Html\Table;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

class DiscoverResultsTable extends Table
{
    use TranslationHelper;

    protected $defaultAttributes = [
        'class' => 'common-table table-row-selectable snmp-scan-results-table',
        'data-base-target' => '_self',
    ];

    protected UuidInterface $nodeUuid;
    protected PdoAdapter $db;

    protected static function sortIdx(string $value): string
    {
        [$ip, $port] = explode(':', $value, 2); // TODO: strrpos, bc of ipv6

        return inet_pton($ip);
    }

    public function __construct(PdoAdapter $db, UuidInterface $nodeUuid, array $results)
    {
        $this->db = $db;
        $ipLookup = new IpToCountryLiteLookup($db);

        $this->nodeUuid = $nodeUuid;
        uasort($results, fn ($a, $b) => self::sortIdx($a->peer) < self::sortIdx($b->peer) ? -1 : 1);
        $this->getHeader()->add(Table::row([
            $this->translate('Peer'),
            $this->translate('sysName: sysDescr'),
        ], null, 'th'));
        $body = $this->getBody();
        foreach ($results as $result) {
            $rowClasses = [];
            [$ip, $port] = explode(':', $result->peer, 2);
            $label = (new IpAddress(inet_pton($ip), $ipLookup))->add(":$port");
            if ($uuid = $this->getExistingAgentUuid($result->peer)) {
                $link = Link::create($label, 'imedge/snmp/device', [
                    'uuid' => $uuid->toString()
                ]);
            } else {
                $rowClasses[] = 'new';
                $link = Link::create($label, 'imedge/snmp/device', [
                    'peer'       => $result->peer,
                    'credential' => $result->credential ?? null,
                    'node'       => $nodeUuid->toString(),
                ]);
            }
            $body->add(Table::row([
                $link,
                $result->label,
            ], ['class' => $rowClasses]));
        }
    }

    protected function getExistingAgentUuid(string $peer): ?UuidInterface
    {
        [$ip, $port] = explode(':', $peer); // TODO: IPv6, strrpos
        $query = $this->db->select()
            ->from('snmp_agent', 'agent_uuid')
            ->where('ip_address = ?', inet_pton($ip))
            ->where('snmp_port = ?', (int) $port);

        if ($uuid = $this->db->fetchOne($query)) {
            return Uuid::fromBytes($uuid);
        }

        return null;
    }
}
