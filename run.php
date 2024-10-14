<?php

use Icinga\Application\Modules\Module;
use Icinga\Module\Imedge\Web\StyleSheetExtender;

/** @var Module $this */
if (StyleSheetExtender::extendIcingaWeb(__DIR__)) {
    return;
}
