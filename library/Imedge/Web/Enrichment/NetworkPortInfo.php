<?php

namespace Icinga\Module\Imedge\Web\Enrichment;

use ipl\Html\BaseHtmlElement;

class NetworkPortInfo
{
    public static function applyTo(BaseHtmlElement $port, $entity)
    {
        $port->addAttributes([
            // data adds +60% soze
            'data-entity-id' => $entity->id,
            'data-if-index'  => $entity->id,
            'data-if-title'  => static::makeTitle($entity),
            'data-if-extra'  => static::makeExtraInfo($entity),
            'data-rel-pos'   => $entity->relative_position,
            'class'          => static::getClasses($entity),
        ]);
        if (isset($entity->imgFileName)) {
            $port->addAttributes([
                'data-if-image' => $entity->imgFileName,
            ]);
        }

        if (isset($entity->agent_uuid)) {
            /*
            $port->getAttributes()->add('data-url', Url::fromPath('imedge/snmp/interface', [
                'agent_uuid' => Uuid::fromBytes($entity->agent_uuid)->toString(),
                'if_index'   => $entity->id, // -> if_index
            ])->getAbsoluteUrl());*/
        }
    }

    protected static function getClasses($entity)
    {
        if ($entity->if_status_duplex === 'halfDuplex') {
            $ifUsage = $entity->if_usage_out + $entity->if_usage_in;
        } else {
            $ifUsage = \max($entity->if_usage_out, $entity->if_usage_in);
        }

        // Random Flicker ;-)
        /*$ifUsage = min([
            \min(1, (\rand(0, 100) / 100)),
            \min(1, (\rand(0, 100) / 100)),
            \min(1, (\rand(0, 100) / 100)),
            \min(1, (\rand(0, 100) / 100)),
        ]);*/

        $states = ['port'];
        $states[] = 'oper_' . $entity->if_status_oper;
        $states[] = 'admin_' . $entity->if_status_admin;

        if ($entity->if_status_oper !== 'up') {
            return $states;
        }

        if ($entity->if_speed_kbit > 1000000) {
            $states[] = 'speed_gbit';
        } elseif ($entity->if_speed_kbit > 100000) {
            $states[] = 'speed_mbit';
        }

        if ($entity->if_status_duplex === 'halfDuplex') {
            $states[] = 'halfduplex';
        }

        if ($entity->if_status_stp === 'blocking') {
            $states[] = 'stp_blocking';
        }

        if ($ifUsage >= 0.85) {
            $states[] = 'usage_85';
        } elseif ($ifUsage >= 0.70) {
            $states[] = 'usage_70';
        } elseif ($ifUsage >= 0.55) {
            $states[] = 'usage_55';
        } elseif ($ifUsage >= 0.4) {
            $states[] = 'usage_40';
        } elseif ($ifUsage >= 0.25) {
            $states[] = 'usage_25';
        } elseif ($ifUsage >= 0.1) {
            $states[] = 'usage_10';
        } elseif ($ifUsage >= 0.01) {
            $states[] = 'usage_1';
        } else {
            $states[] = 'usage_0';
        }

        return $states;
    }

    protected static function makeTitle($entity)
    {
        if ($entity->if_alias) {
            return \sprintf('%s (%s)', $entity->if_name, $entity->if_alias);
        }

        return $entity->if_name;
    }

    protected static function makeExtraInfo($entity)
    {
        $extra = [];

        // $ifUsage = max($entity->if_usage_out, $entity->if_usage_in);

        $extra[] = $entity->if_status_admin . '/' . $entity->if_status_oper;

        if ($entity->if_status_duplex === 'halfDuplex') {
            $extra[] = 'half duplex';
        }

        if ($entity->if_status_stp === 'blocking') {
            $extra[] = 'STP is blocking';
        }

        $extra[] = static::formatIfSpeed($entity->if_speed_kbit);

        if ($entity->if_status_duplex === 'halfDuplex') {
            $ifUsage = $entity->if_usage_out + $entity->if_usage_in;
        } else {
            $ifUsage = \max($entity->if_usage_out, $entity->if_usage_in);
        }

        // $extra[] = \sprintf('Usage: %.2F%%', $ifUsage * 100);

        if (empty($extra)) {
            return '';
        } else {
            return \implode(', ', $extra);
        }
    }

    protected static function formatIfSpeed($speedKbit)
    {
        if ($speedKbit >= 1000000) {
            return \sprintf('%d GBit/s', $speedKbit / 1000000);
        } elseif ($speedKbit >= 1000) {
            return \sprintf('%d MBit/s', $speedKbit / 1000);
        } else {
            return \sprintf('%d KBit/s', $speedKbit);
        }
    }
}
