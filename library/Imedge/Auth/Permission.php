<?php

namespace Icinga\Module\Imedge\Auth;

class Permission
{
    public const GLOBAL_ADMIN = 'imedge/globalAdmin';
    // public const TENANT_ADMIN = 'imedge/admin';
    public const DEVICE_ALL = 'imedge/device/*';
    public const DEVICE_READ = 'imedge/device/read';
    public const DEVICE_WRITE = 'imedge/device/write';
    public const DEVICE_DELETE = 'imedge/device/delete';
    public const CREDENTIALS_ALL = 'imedge/credentials/*';
    public const CREDENTIALS_READ = 'imedge/credentials/read';
    public const CREDENTIALS_WRITE = 'imedge/credentials/write';
    public const CREDENTIALS_DELETE = 'imedge/credentials/delete';
    public const DISCOVERY_RULE_READ = 'imedge/discoveryRule/read';
    public const DISCOVERY_RULE_ALL = 'imedge/discoveryRule/*';
    public const DISCOVERY_RULE_WRITE = 'imedge/discoveryRule/write';
    public const DISCOVERY_RULE_DELETE = 'imedge/discoveryRule/delete';
    public const DISCOVERY_JOB_READ = 'imedge/discoveryJob/read';
    public const DISCOVERY_JOB_ALL = 'imedge/discoveryJob/*';
    public const DISCOVERY_JOB_WRITE = 'imedge/discoveryJob/write';
    public const DISCOVERY_JOB_DELETE = 'imedge/discoveryJob/delete';
    public const DISCOVERY_JOB_CONTROL = 'imedge/discoveryJob/control';
}
