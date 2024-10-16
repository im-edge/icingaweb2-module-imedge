<?php

namespace Icinga\Module\Imedge\Controllers;

trait WebClientInfo
{
    protected function wantsDarkMode(): bool
    {
        return $this->getServerRequest()->getHeaderLine('X-IMEdge-ColorScheme') === 'dark';
    }
}
