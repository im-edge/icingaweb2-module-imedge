<?php

namespace Icinga\Module\Imedge\Snmp;

use InvalidArgumentException;
use stdClass;
use ValueError;

class VarBindValue
{
    protected string $type;
    protected $value;

    /**
     * @param int|string|null $value
     */
    public function __construct(string $type, $value)
    {
        $this->type = $type;
        $this->value = $value;
    }

    public static function fromSerialization(stdClass $any): VarBindValue
    {
        $type = $any->type ?? 'unspecified';
        switch ($type) {
            case 'bit_string':
            case 'nsap_address':
            case 'octet_string':
            case 'opaque':
                $value = self::stringFromJson($any->value);
                break;
            case 'context_specific':
                $value = ContextSpecificError::intToName($any->value);
                break;
            case 'ip_address':
                $value = inet_pton($any->value);
                break;
            case 'counter32':
            case 'counter34':
            case 'integer32':
            case 'gauge32':
            case 'oid':
            case 'time_ticks':
            case 'unsigned32':
                $value = $any->value;
                break;
            case 'null':
                $value = null;
                break;
            default:
                throw new InvalidArgumentException("$type is not a valid VarbindValue type");
        };
        return new VarBindValue($type, $value);
    }

    /**
     * @return int|string|null
     */
    public function getReadableValue()
    {
        switch ($this->type) {
            case 'bit_string':
            case 'nsap_address':
            case 'octet_string':
            case 'opaque':
                return self::getReadableString($this->value);
            case 'ip_address':
                return inet_ntop($this->value);
            default:
                return $this->value;
        }
    }

    protected static function stringFromJson(string $string): string
    {
        if (str_starts_with($string, '0x')) {
            $binary = @hex2bin(substr($string, 2));
            if ($binary === false) {
                throw new ValueError("Cannot decode '$string'");
            }

            return $binary;
        }

        return $string;
    }

    public static function getReadableString(string $string): string
    {
        if (self::isUtf8Safe($string)) {
            return $string;
        }

        return '0x' . bin2hex($string);
    }

    public static function stringForJson(string $string): string
    {
        if (!str_starts_with($string, '0x') && self::isUtf8Safe($string)) {
            return $string;
        }

        return '0x' . bin2hex($string);
    }

    protected static function isUtf8Safe(string $string): bool
    {
        return preg_match('//u', $string) !== false;
    }
}
