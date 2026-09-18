<?php

namespace Icinga\Module\Imedge\Controllers;

use Icinga\Module\Imedge\Auth\TenantHandler;
use Ramsey\Uuid\UuidInterface;

trait TenantTrait
{
    protected static ?TenantHandler $tenantHandler = null;

    protected function getTenantUuid(): ?UuidInterface
    {
        return (self::$tenantHandler ??= new TenantHandler($this->Window()))->getActiveUuid();
    }
}
