<?php

namespace Icinga\Module\Imedge\ProvidedHook\Director;

use Icinga\Module\Director\Hook\ImportSourceHook;
use Icinga\Module\Director\Web\Form\QuickForm;
use Icinga\Module\Imedge\Config\Defaults;
use Icinga\Module\Imedge\Db\DbFactory;
use IMEdge\Web\Data\Model\SnmpAgent;
use IMEdge\Web\Data\Model\SnmpSystemInfo;
use Ramsey\Uuid\Uuid;

class SnmpDeviceImportSource extends ImportSourceHook
{
    public function getName()
    {
        return mt(Defaults::MODULE_NAME, 'SNMP Devices (with credentials)');
    }

    public static function addSettingsFormFields(QuickForm $form)
    {
        $form->addElement('select', 'lifecycle_uuid', [
            'label'        => mt(Defaults::MODULE_NAME, 'Lifecycle'),
            'multiOptions' => self::fetchEnum('system_lifecycle'),
        ]);
        $form->addElement('select', 'environment_uuid', [
            'label'        => mt(Defaults::MODULE_NAME, 'Environment'),
            'multiOptions' => self::fetchEnum('system_environment'),
        ]);
    }

    protected static function fetchEnum($table, $uuidColumn = 'uuid', $labelColumn = 'label'): array
    {
        $db = DbFactory::db();
        $values = [];
        foreach ($db->fetchPairs($db->select()->from($table, [$uuidColumn, $labelColumn])) as $uuid => $label) {
            $values[Uuid::fromBytes($uuid)->toString()] = $label;
        }

        return [null => mt(Defaults::MODULE_NAME, '- please choose -')] + $values;
    }

    public function fetchData()
    {
        $db = DbFactory::db();
        $query = $db->select()->from(
            ['sa' => SnmpAgent::TABLE],
            [
                'sa.ip_address',
                'system_name' => 'COALESCE(sa.label, ssi.system_name)',
                'sc.snmp_version',
                'snmp_community'      => 'CASE WHEN sc.snmp_version = 3 THEN NULL ELSE sc.security_name END',
                'snmp_security_name'  => 'CASE WHEN sc.snmp_version = 3 THEN sc.security_name ELSE NULL END',
                'snmp_security_level' => 'CASE WHEN sc.snmp_version = 3 THEN sc.security_level ELSE NULL END',
                'snmp_auth_protocol'  => 'sc.auth_protocol',
                'snmp_auth_key'       => 'sc.auth_key',
                'snmp_priv_protocol'  => 'sc.priv_protocol',
                'snmp_priv_key'       => 'sc.priv_key',
            ]
        )->join(
            ['ssi' => SnmpSystemInfo::TABLE],
            'ssi.uuid = sa.agent_uuid',
            []
        )->join(
            ['sc' => 'snmp_credential'],
            'sc.credential_uuid = sa.credential_uuid',
            []
        )
        ->order('COALESCE(sa.label, ssi.system_name)')
        ->order('sa.ip_address');
        if ($lifeCycle = $this->getSetting('lifecycle_uuid')) {
            $query->where('sa.lifecycle_uuid = ?', Uuid::fromString($lifeCycle)->getBytes());
        }
        if ($environment = $this->getSetting('environment_uuid')) {
            $query->where('sa.environment_uuid = ?', Uuid::fromString($environment)->getBytes());
        }

        $rows = [];
        foreach ($db->fetchAll($query) as $row) {
            $row->ip_address = inet_ntop($row->ip_address);
            $rows[] = $row;
        }

        return $rows;
    }

    public function listColumns()
    {
        return [
            'ip_address',
            'system_name',
            'snmp_version',
            'snmp_community',
            'snmp_security_name',
            'snmp_security_level',
            'snmp_auth_protocol',
            'snmp_auth_key',
            'snmp_priv_protocol',
            'snmp_priv_key',
        ];
    }
}
