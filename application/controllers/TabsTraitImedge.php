<?php

namespace Icinga\Module\Imedge\Controllers;

use gipfl\IcingaWeb2\Widget\Tabs;
use Icinga\Module\Imedge\Config\Defaults;

trait TabsTraitImedge
{
    protected function imedgeTabs(): Tabs
    {
        return $this->tabs()->add('imedge', [
            'label' => Defaults::APPLICATION_NAME,
            'url'   => 'imedge'
        ])->add('storage', [
            'label' => $this->translate('Metric Stores'),
            'url' => 'imedge/metrics'
        ]);
    }
}
