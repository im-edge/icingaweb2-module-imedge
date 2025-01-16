<?php

namespace Icinga\Module\Imedge\Web\Table\Snmp;

use gipfl\IcingaWeb2\Table\ZfQueryBasedTable;
use gipfl\ZfDb\Adapter\Pdo\PdoAdapter;
use Icinga\Module\Imedge\Graphing\RrdImageLoader;
use IMEdge\Web\Grapher\GraphModifier\Modifier;
use IMEdge\Web\Grapher\GraphRendering\ImedgeGraphPreview;
use ipl\Html\Html;
use Ramsey\Uuid\UuidInterface;

class SnmpEntitySensorsTable extends ZfQueryBasedTable
{
    protected $searchColumns = [
        'e.name',
        'e.alias'
    ];
    protected $lastParent = null;

    protected UuidInterface $systemUuid;
    protected RrdImageLoader $imageLoader;

    public function __construct(PdoAdapter $db, UuidInterface $systemUuid)
    {
        parent::__construct($db);
        $this->systemUuid = $systemUuid;
        $this->imageLoader = new RrdImageLoader($db);
    }

    protected function assemble()
    {
        $this->getAttributes()->add([
            'style' => 'width: 100%; max-width: unset'
        ]);
    }

    protected function addParentRow($row)
    {
        $this->add(self::tr([
            self::th(sprintf(
                '%s: %s, %s, s/n %s',
                $row->parent_class,
                $row->parent_name,
                $row->parent_description ?: $row->parent_model,
                $row->parent_serial
            ), [
                'colspan' => 3
            ])
        ]));
    }

    public function renderRow($row)
    {
        if ($this->lastParent !== $row->parent_index) {
            $this->addParentRow($row);
            $this->lastParent = $row->parent_index;
        }
        $unit = $row->sensor_units_display;
        if ($unit === null) { // These are ugly workarounds, display hint should be there
            if ($row->sensor_scale === 'units') {
                $unit = $row->sensor_type;
            } else {
                $unit = $row->sensor_scale . ' ' . $row->sensor_type;
            }
        }

        $row->adjusted_value = $row->sensor_value / pow(10, $row->sensor_precision) . ' ' . $unit;
        if ($row->sensor_status !== 'ok') {
            $row->adjusted_value = sprintf($this->translate('Sensor is %s'), $row->sensor_status);
        } elseif ($row->sensor_type === 'truthvalue') {
            switch ((int) $row->sensor_value) {
                case 1:
                    $row->adjusted_value = 'true';
                    break;
                case 2:
                    $row->adjusted_value = 'false';
                    break;
                default:
                    $row->adjusted_value = 'Invalid: ' . $row->sensor_value;
            }
        }
        unset(
            $row->sensor_type,
            $row->sensor_scale,
            $row->sensor_units_display,
            $row->sensor_precision,
            $row->sensor_value,
            $row->sensor_status,
            $row->parent_class,
            $row->parent_name,
            $row->parent_description,
            $row->parent_model,
            $row->parent_manufacturer,
            $row->parent_serial,
        );

        return static::row([
            Html::tag('li', $row->description ?: $row->name),
            $row->adjusted_value,
            Html::tag('div', ['style' => 'max-height: 80px'], $this->getImage($row, $unit)),
        ]);
        // return static::row((array) $row);
    }

    protected function getImage($row, $unit)
    {
        $image = $this->imageLoader->getDeviceImg(
            $this->systemUuid,
            'entity_sensor',
            $row->entity_index,
            'entitySensor'
        );
        if (!$image) {
            return null;
        }

        // $image->loadImmediately();
        $image->graph->format->setFormat('svg');
        $image->graph->getTimeRange()->setStart('now-16hours');
        $image->graph->layout->disableXAxis();
        if ($unit === 'Celsius') {
            // Hint: doesn't work. We should either generate on-the-fly-templates, or allow to pass transformations via
            //       URL. Idea -> pass a palette and/or color offset!?
            $image->graph->definition = Modifier::replaceInstructionColor(
                $image->graph->definition,
                'E6B40C',
                'FF000066'
            );
        }
        $container = new ImedgeGraphPreview($image);
        $container->addAttributes(['style' => 'width: 40em; height: 8em;']);
        return $container;
    }

    public function prepareQuery()
    {
        // Hint: real object -> parent?
        // TODO: if no agent -> join all of them, add columns
        return $this->db()->select()
            ->from(['e' => 'inventory_physical_entity'], [
                'entity_index'            => 'e.entity_index',
                'parent_index'           => 'e.parent_index',
                'name'                => 'e.name',

                'parent_class'            => 'pe.class',
                'parent_name'             => 'pe.name',
                'parent_description'      => 'pe.description',
                'parent_model'            => 'pe.model_name',
                'parent_serial'           => 'pe.serial_number',
                'parent_manufacturer'     => 'pe.manufacturer_name',
                // 'class'                => 'e.class', -> always 'sensor'

                'description'            => 'description',

                'sensor_type' => 'es.sensor_type',
                'sensor_scale' => 'es.sensor_scale',
                'sensor_precision' => 'es.sensor_precision',
                'sensor_status' => 'es.sensor_status',
                'sensor_value' => 'es.sensor_value',
                'sensor_units_display' => 'es.sensor_units_display',
            ])
            ->join(
                ['es' => 'inventory_physical_entity_sensor'],
                'e.device_uuid = es.device_uuid AND e.entity_index = es.entity_index',
                []
            )->joinLeft( // are there sensors w/o parent? Check once we have enough data
                ['pe' => 'inventory_physical_entity'],
                'e.device_uuid = pe.device_uuid AND e.parent_index = pe.entity_index',
                []
            )
            ->where('e.device_uuid = ?', $this->systemUuid->getBytes())
            ->limit(100)
            ->order('e.parent_index')
            ->order('e.relative_position');
    }
}
