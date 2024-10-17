<?php

namespace Icinga\Module\Imedge\ProvidedHook\Director;

use Icinga\Module\Director\Hook\ImportSourceHook;
use Icinga\Module\Director\Web\Form\QuickForm;
use Icinga\Module\Imedge\Config\Defaults;
use Icinga\Module\Imedge\Db\DbFactory;
use IMEdge\Web\Data\Helper\MacAddressHelper;
use IMEdge\Web\Data\Model\NetworkInterfaceConfig;
use IMEdge\Web\Data\Model\SnmpAgent;
use IMEdge\Web\Data\Model\SnmpSystemInfo;
use Ramsey\Uuid\Uuid;

class NetworkInterfaceImportSource extends ImportSourceHook
{
    public function getName()
    {
        return mt(Defaults::MODULE_NAME, 'Network Interfaces (SNMP)');
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
                'nic.if_index',
                'nic.if_type',
                'nic.if_name',
                'nic.if_alias',
                'nic.if_description',
                'nic.physical_address',
            ]
        )->join(
            ['ssi' => SnmpSystemInfo::TABLE],
            'ssi.uuid = sa.agent_uuid',
            []
        )->join(
            ['nic' => NetworkInterfaceConfig::TABLE],
            'nic.system_uuid = sa.agent_uuid',
            []
        )
        ->where('nic.monitor = ?', 'y')
        ->order('COALESCE(sa.label, ssi.system_name)')
        ->order('sa.ip_address')
        ->order('nic.if_index');
        if ($lifeCycle = $this->getSetting('lifecycle_uuid')) {
            $query->where('sa.lifecycle_uuid = ?', Uuid::fromString($lifeCycle)->getBytes());
        }
        if ($environment = $this->getSetting('environment_uuid')) {
            $query->where('sa.environment_uuid = ?', Uuid::fromString($environment)->getBytes());
        }
        $rows = [];
        foreach ($db->fetchAll($query) as $row) {
            $row->ip_address = inet_ntop($row->ip_address);
            if (null !== ($mac = $row->physical_address)) {
                $row->physical_address = MacAddressHelper::toText($mac);
            }
            $rows[] = $row;
        }

        return $rows;
    }

    public function listColumns()
    {
        return [
            'ip_address',
            'system_name',
            'if_index',
            'if_type',
            'if_name',
            'if_alias',
            'if_description',
            'physical_address',
        ];
    }
}
