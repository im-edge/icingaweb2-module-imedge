<?php

namespace Icinga\Module\Imedge\Web\Table\Inventory;

use gipfl\IcingaWeb2\Link;
use gipfl\IcingaWeb2\Table\ZfQueryBasedTable;
use Ramsey\Uuid\Uuid;

class CredentialsTable extends ZfQueryBasedTable
{
    protected $searchColumns = [
        'credential_name',
        'security_name'
    ];

    public function getColumnsToBeRendered()
    {
        return array(
            $this->translate('Credential name'),
            $this->translate('SNMP version'),
            $this->translate('Authentication'),
            $this->translate('Encryption'),
        );
    }

    public function renderRow($row)
    {
        return static::row([
            Link::create($row->credential_name, 'imedge/snmp/credential', [
                'uuid' => Uuid::fromBytes($row->credential_uuid)->toString()
            ]),
            $row->snmp_version,
            $row->security_level !== 'noAuthNoPriv' ? strtoupper($row->auth_protocol) : '-',
            $row->security_level === 'authPriv' ? strtoupper($row->priv_protocol) : '-'
        ]);
    }

    public function prepareQuery()
    {
        return $this->db()->select()
            ->from('snmp_credential', [
                'credential_uuid',
                'credential_name',
                'snmp_version',
                'security_level',
                'auth_protocol',
                'priv_protocol',
            ])->order('credential_name');
    }
}
