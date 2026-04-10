<?php

namespace Icinga\Module\Imedge\Snmp;

use IMEdge\Json\JsonSerialization;

class VarBindList implements JsonSerialization
{
    /** @var VarBind[] */
    public array $varBinds = [];

    /**
     * @param VarBind[] $varBinds
     */
    public function __construct(array $varBinds = [])
    {
        $this->varBinds = $varBinds;
    }

    public function hasOid(string $oid): bool
    {
        foreach ($this->varBinds as $varBind) {
            if ($varBind->oid === $oid) {
                return true;
            }
        }

        return false;
    }

    public function getOptionalValueForOid(string $oid): ?VarBindValue
    {
        foreach ($this->varBinds as $varBind) {
            if ($varBind->oid === $oid) {
                return $varBind->value;
            }
        }

        return null;
    }

    /**
     * @return array<string, ?VarBindValue>
     */
    public function getValuesIndexedByOid(): array
    {
        $result = [];
        foreach ($this->varBinds as $varBind) {
            $result[$varBind->oid] = $varBind->value;
        }

        return $result;
    }

    /**
     * @return static|VarBindList
     */
    public static function fromSerialization($any): VarBindList
    {
        if (! is_array($any)) {
            throw new \RuntimeException('VarBindList needs an array');
        }
        $varBinds = [];
        foreach ($any as $varBind) {
            $varBinds[] = VarBind::fromSerialization($varBind);
        }

        return new VarBindList($varBinds);
    }

    public function jsonSerialize(): array
    {
        return $this->varBinds;
    }
}
