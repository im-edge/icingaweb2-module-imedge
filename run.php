<?php

use Icinga\Application\Modules\Module;
use Icinga\Module\Imedge\ProvidedHook\Director\NetworkInterfaceImportSource;
use Icinga\Module\Imedge\ProvidedHook\Director\SnmpDeviceImportSource;
use Icinga\Module\Imedge\Web\StyleSheetExtender;

/** @var Module $this */
if (StyleSheetExtender::extendIcingaWeb(__DIR__)) {
    return;
}

require_once __DIR__ . '/vendor/autoload.php';
$this->provideHook('director/ImportSource', NetworkInterfaceImportSource::class);
$this->provideHook('director/ImportSource', SnmpDeviceImportSource::class);
