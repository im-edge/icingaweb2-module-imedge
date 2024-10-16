<?php

namespace Icinga\Module\Imedge\Controllers;

use gipfl\IcingaWeb2\Link;

trait SpecialActions
{
    protected function addInventoryTab(): void
    {
        $this->tabs()->add('inventory', [
            'label' => $this->translate('Inventory'),
            'icon' => 'home',
            'url' => 'imedge/inventory',
        ]);
    }

    protected function linkBack(string $url)
    {
        $this->actions()->add(
            Link::create($this->translate('Back'), $url, null, [
                'class' => 'icon-left-big',
            ])
        );
    }
}
