<?php

namespace Icinga\Module\Imedge\Auth;

use Icinga\Web\Session\SessionNamespace;
use Icinga\Web\Window;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

class TenantHandler
{
    protected const SESSION_NAMESPACE = 'imedge';
    protected const SESSION_KEY = 'activeTenant';

    protected Window $sessionWindow;

    public function __construct(Window $sessionWindow)
    {
        $this->sessionWindow = $sessionWindow;
    }

    public function activate(UuidInterface $uuid): void
    {
        static::session()->set(self::SESSION_KEY, $uuid->toString());
    }

    public function clearFromSession(): void
    {
        static::session()->set(self::SESSION_KEY, null);
    }

    public function getActiveUuid(): ?UuidInterface
    {
        $uuid = static::session()->get(self::SESSION_KEY);
        if ($uuid === null) {
            return TenantRestrictions::getPrimaryTenantUuid();
        }

        return Uuid::fromString($uuid);
    }

    protected function session(): SessionNamespace
    {
        return $this->sessionWindow->getSessionNamespace(self::SESSION_NAMESPACE);
    }
}
