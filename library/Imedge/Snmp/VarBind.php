<?php

namespace Icinga\Module\Imedge\Snmp;

use IMEdge\Json\JsonSerialization;
use RuntimeException;

class VarBind implements JsonSerialization
{
    /** @readonly */
    public string $oid;
    /** @readonly */
    public ?VarBindValue $value = null;

    final public function __construct(string $oid, ?VarBindValue $value = null)
    {
        $this->oid = $oid;
        $this->value = $value;
    }

    /**
     * @return static|VarBind
     */
    public static function fromSerialization($any): VarBind
    {
        if (! is_array($any) || count($any) !== 2) {
            throw new RuntimeException('VarBind needs an array with two elements');
        }
        return new VarBind($any[0], VarBindValue::fromSerialization($any[1]));
    }

    /**
     * @return array{0: string, 1: ?VarBindValue}
     */
    public function jsonSerialize(): array
    {
        return [$this->oid, $this->value];
    }
}
