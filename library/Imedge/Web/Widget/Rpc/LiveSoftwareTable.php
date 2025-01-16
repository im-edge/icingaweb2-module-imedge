<?php

namespace Icinga\Module\Imedge\Web\Widget\Rpc;

use gipfl\Translation\TranslationHelper;
use ipl\Html\Table;

class LiveSoftwareTable extends Table
{
    use TranslationHelper;

    public function __construct($software)
    {
        $this->getHeader()->add(
            $this::row([
                $this->translate('Package / Software'),
                $this->translate('Epoch'),
                $this->translate('Version'),
                $this->translate('Architecture'),
                $this->translate('Type'),
            ], null, 'th')
        );

        foreach ($software as $row) {
            $nameString = SnmpValue::getReadableSnmpValue($row->name);
            $parts = explode('_', $nameString);
            $name = array_shift($parts);
            $version = array_shift($parts);
            // Might be missing with long strings, like in the following 63 byte string:
            // "ipxe-qemu-256k-compat-efi-roms_1.0.0+git-20150424.a25a16d-0ubun"
            $architecture = array_shift($parts);

            // Hint: got 9 or 9.2 as software version AND version in the package name on RH:
            if ($version === null || preg_match('/^\d+(?:\.ḑ+)?$/', $version)) {
                if (preg_match('/^(.+?)-(\d.+)$/', $name, $match)) {
                    $name = $match[1];
                    $version = $match[2];
                }
            }
            if (strpos($version ?? '', ':') === false) {
                if (strpos($version ?? '', '-') === false) {
                    $epoch = null;
                } else {
                    list($epoch, $version) = explode('-', $version, 2);
                }
            } else {
                list($epoch, $version) = explode(':', $version, 2);
            }
            $this->add($this::row([
                $name,
                $epoch,
                $version,
                $architecture,
                $this->getTypeName(SnmpValue::getReadableSnmpValue($row->type)),
            ]));
        }
    }

    protected function getTypeName($type)
    {
        switch ($type) {
            case 1:
                return 'unknown';
            case 2:
                return 'operatingSystem';
            case 3:
                return 'deviceDriver';
            case 4:
                return 'application';
            default:
                return "$type ??";
        }
    }
}
