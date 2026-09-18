<?php

namespace Icinga\Module\Imedge\Auth;

use gipfl\ZfDb\Select;
use Icinga\Authentication\Auth;
use IMEdge\Web\Data\Model\UuidObject;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

class TenantRestrictions
{
    public const NO_TENANT = 'ffffffff-ffff-ffff-ffff-ffffffffffff';

    protected static ?array $restrictions = null;

    public static function hasRestriction(): bool
    {
        return !empty(self::getRestrictions());
    }

    public static function applyFilter(
        Select $select,
        string $filterColumn = 'tenant_uuid',
        ?UuidInterface $chosenUuid = null
    ): Select {
        if ($chosenUuid !== null) {
            return $select->where("$filterColumn = ?", $chosenUuid->getBytes());
        }
        if (! self::hasRestriction()) {
            return $select;
        }

        $list = self::listBinaryTenants();
        if (empty($list)) {
            $list = [Uuid::fromString(self::NO_TENANT)->getBytes()];
        }

        return $select->where("$filterColumn IN (?)", $list);
    }

    /**
     * @return array<string, UuidInterface>
     */
    public static function getRestrictions(): array
    {
        return self::$restrictions ??= self::fetchRestrictions();
    }

    public static function getPrimaryTenantUuid(): ?UuidInterface
    {
        if (self::hasRestriction()) {
            $list = self::getRestrictions();
            return array_shift($list);
        }

        return null;
    }


    /**
     * @return string[]
     */
    protected static function listBinaryTenants(): array
    {
        $result = [];
        foreach (self::getRestrictions() as $uuid) {
            $result[] = $uuid->getBytes();
        }

        return $result;
    }

    /**
     * @return array<string, UuidInterface>
     */
    protected static function fetchRestrictions(): array
    {
        $tenants = [];
        foreach (Auth::getInstance()->getRestrictions(Restriction::TENANTS) as $restriction) {
            foreach (preg_split('/\s?,\s?/', trim($restriction)) as $part) {
                $uuid = Uuid::fromString($part);
                $tenants[$uuid->toString()] = $uuid;
            }
        }

        return $tenants;
    }

    public static function allows(UuidObject $agent): bool
    {
        if (!self::hasRestriction()) {
            return true;
        }

        return in_array($agent->getUuid()->getBytes(), self::listBinaryTenants(), true);
    }
}
