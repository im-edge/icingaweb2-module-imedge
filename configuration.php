<?php

use Icinga\Application\Modules\Module;
use Icinga\Module\Imedge\Auth\Permission;

/** @var Module $this */
$section = $this->menuSection('IMEdge')
    ->setPermission(Permission::ADMIN)
    ->setUrl('imedge')
    ->setPriority(80)
    ->setIcon('services');

$section = $this->menuSection(N_('Inventory'))
    ->setUrl('imedge/inventory')
    ->setPriority(50)
    ->setIcon('th-list');
$section->add(N_('Devices'))
    ->setUrl('imedge/snmp/devices')
    ->setPriority(10);
$section->add(N_('Credentials'))
    ->setUrl('imedge/snmp/credentials')
    ->setPriority(20);
$section->add(N_('Discovery'))
    ->setUrl('imedge/discovery/candidates')
    ->setPriority(30);
$section->add(N_('Monitoring Nodes'))
    ->setUrl('imedge/nodes')
    ->setPriority(40);

$this->provideJsFile('combined.js');
