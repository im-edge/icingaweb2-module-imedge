<?php

namespace Icinga\Module\Imedge\Web\Table\ShowCase;

use gipfl\Translation\TranslationHelper;
use gipfl\ZfDb\Adapter\Adapter;
use IMEdge\Web\Data\Lookup\MacAddressBlockLookup;
use IMEdge\Web\Data\Widget\MacAddress;
use ipl\Html\Html;
use ipl\Html\Table;

class SampleMacAddressTable extends Table
{
    use TranslationHelper;

    protected Adapter $db;

    public function __construct(Adapter $db)
    {
        $this->db = $db;
    }

    protected function assemble()
    {
        $macs = [
            'c8:2e:18:0a:f7:44', // Shelly Plus 1
            '78-98-E8-FA-6D-A0',
            'D4-01-C3-53-57-F3',
            '50:E6:36:3C:7B:2E', // Nochmal AVM?
            'C8-0E-14-CB-BF-DF', // Fritzbox, (192.168.178.1), AVM
            '001c7fa1a1d9', // Checkpoint - Guest LAN GW
            '00-00-5E-00-01-17', // VRRPv4
            '00-00-5E-00-02-fa', // VRRPv6
            'ac4e9143c110', // Huawei
            // Port 1
            'A4-4B-D5-2C-39-06', //Xiaomi - >Handy Bianca?
            // Port 2
            '3c:2a:f4:67:34:96', // BRN3C2AF4673496 (192.168.178.39) Brother (HL-L3270CDW series)
            '40-8D-5C-55-6A-29', // 40:8d:5c:55:6a:29 -> BIG11 (192.168.178.25), GIGA-BYTE
            '0e:0a:81:8d:27:c0', // Random MAC example
            'f0:ff:f0:ab:bd:00', // Unknown MAC example
            '00-50-C2-00-30-00',
            '5C-6A-80-FC-37-FA', // Zyxel -> Switch hinten
            '00-01-6C-5D-17-39', // 00:01:6c:5d:17:39 -> SKYSURFER (192.168.178.21), FOXCONN

            // Port 3?
            '18-31-BF-C8-C4-B3', // 18:31:bf:c8:c4:b3 -> pc-schmitt (192.168.178.72), ASUSTek
            '00:1B:C5:02:63:45', // OUI36


            // Port 4, alter PC Schmitt
            '00-19-21-E0-AF-42', // 00:19:21:e0:af:42 -> PC (192.168.178.22), Elitegroup
            '01005e7f83c8', // Multicast example

            // Port 5
            '2C-F0-5D-80-3B-76', // so-office03 (192.168.178.43), Micro-Star

            // Port 6
            '30-05-5C-20-B2-06', // Brother MFC-7460DN

            // Port 7
            '00-E0-4C-F8-FF-1B', // 00:e0:4c:f8:ff:1b -> so-srv1 (192.168.178.51), REALTEK
            'f0:2a:2b:5d:f0:71', // MA-M assignment: F0-2A-2B-5
        ];
        $lookup = new MacAddressBlockLookup($this->db);
        $this->getHeader()->add(Table::row([
            $this->translate('MAC Address'),
            $this->translate('Vendor'),
        ], null, 'th'));
        foreach ($macs as $search) {
            $macAddress = MacAddress::parse($search, $lookup);
            $this->add(Table::row([
                $macAddress,
                [
                    $macAddress->description,
                    $macAddress->additionalInfo ? [Html::tag('br'), $macAddress->additionalInfo] : null
                ],
            ]));
        }
    }
}
