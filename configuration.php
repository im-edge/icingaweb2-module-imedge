<?php

use Icinga\Application\Modules\Module;
use Icinga\Module\Imedge\Auth\Permission;
use Icinga\Module\Imedge\Auth\Restriction;

/** @var Module $this */
$this->providePermission(
    Permission::GLOBAL_ADMIN,
    $this->translate('Grant administrative IMEdge access (allows to manage nodes, tenants and more)')
);
$this->providePermission(Permission::CREDENTIALS_ALL, $this->translate('Full access to credentials'));
$this->providePermission(Permission::CREDENTIALS_READ, $this->translate('List configured credentials'));
$this->providePermission(Permission::CREDENTIALS_WRITE, $this->translate('Create and modify credentials'));
$this->providePermission( Permission::CREDENTIALS_DELETE, $this->translate('Delete credentials'));
$this->providePermission(Permission::DEVICE_ALL, $this->translate('Full access to devices'));
$this->providePermission(Permission::DEVICE_READ, $this->translate('Show configured devices and related details'));
$this->providePermission(Permission::DEVICE_WRITE, $this->translate('Create and modify devices'));
$this->providePermission(Permission::DEVICE_DELETE, $this->translate('Delete devices'));
$this->providePermission(Permission::DISCOVERY_RULE_ALL, $this->translate('Full access to discovery rules'));
$this->providePermission(Permission::DISCOVERY_RULE_READ, $this->translate('Show configured discovery rules'));
$this->providePermission(Permission::DISCOVERY_RULE_WRITE, $this->translate('Create and modify discovery rules'));
$this->providePermission(Permission::DISCOVERY_RULE_DELETE, $this->translate('Delete discovery rules'));
$this->providePermission(Permission::DISCOVERY_JOB_ALL, $this->translate('Full access to discovery jobs'));
$this->providePermission(Permission::DISCOVERY_JOB_READ, $this->translate('Show configured discovery jobs'));
$this->providePermission(Permission::DISCOVERY_JOB_WRITE, $this->translate('Create and modify discovery jobs'));
$this->providePermission(Permission::DISCOVERY_JOB_DELETE, $this->translate('Delete discovery jobs'));
$this->providePermission(Permission::DISCOVERY_JOB_CONTROL,$this->translate('Control discovery jobs (schedule, run)'));
/*
$this->providePermission(
    Permission::TENANT_ADMIN,
    $this->translate('Grant administrative IMEdge access at Tenant level')
);
*/
$this->provideRestriction(
    Restriction::TENANTS,
    $this->translate('Restrict access to a given tenant. Can be a UUID or a name')
);

$section = $this->menuSection('IMEdge')
    ->setPermission(Permission::GLOBAL_ADMIN)
    ->setUrl('imedge')
    ->setPriority(80)
    ->setIcon('services');

$section = $this->menuSection(N_('Inventory'))
    ->setUrl('imedge/inventory')
    ->setPriority(50)
    ->setIcon('th-list');
$section->add(N_('Devices'))
    ->setPermission(Permission::DEVICE_READ)
    ->setUrl('imedge/snmp/devices')
    ->setPriority(10);
$section->add(N_('Credentials'))
    ->setPermission(Permission::CREDENTIALS_READ)
    ->setUrl('imedge/snmp/credentials')
    ->setPriority(20);
$section->add(N_('Discovery'))
    ->setPermission(Permission::DISCOVERY_JOB_READ)
    ->setUrl('imedge/discovery/jobs')
    ->setPriority(30);
/*
$section->add(N_('Monitoring Nodes'))
    ->setUrl('imedge/nodes')
    ->setPriority(40);
*/

$this->provideJsFile('combined.js');
