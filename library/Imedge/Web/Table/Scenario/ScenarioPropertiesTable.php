<?php

namespace Icinga\Module\Imedge\Web\Table\Scenario;

use gipfl\Translation\TranslationHelper;
use ipl\Html\Html;
use ipl\Html\HtmlElement;
use ipl\Html\Table;
use stdClass;

class ScenarioPropertiesTable extends Table
{
    use TranslationHelper;

    protected $defaultAttributes = [
        'class'            => 'common-table table-row-selectable',
        'data-base-target' => '_next',
    ];

    public function __construct(stdClass $scenario)
    {
        $this->getHeader()->add(Table::row([
            $this->translate('Property'),
            $this->translate('Type'),
            $this->translate('OID / Value, Manglers'),
            $this->translate('DB Column'),
            $this->translate('Metric'),
        ], null, 'th'));
        foreach ($scenario->properties as $property) {
            $this->add([
                $property->name,
                $this->renderType($property),
                [
                    $property->oid ?? null,
                    $property->value ?? null ? $this->renderCallable($property->value) : null,
                    $property->manglers ?? null ? [Html::tag('br'), $this->renderManglers($property->manglers)] : null,
                ],
                $property->dbColumn ?? '-',
                $property->metric ?? null ? sprintf('%s (%s)', $property->metric[0], $property->metric[1]) : '-',
            ]);
        }
    }

    protected function renderManglers(array $manglers): HtmlElement
    {
        $result = Html::tag('ul');
        foreach ($manglers as $mangler) {
            $result->add(Html::tag('li', $this->renderCallable($mangler)));
        }

        return $result;
    }

    protected function renderCallable(array $callable): string
    {
        return sprintf('%s(%s)', $callable[0], implode(', ', $callable[1] ?? []));
    }

    protected function renderType(stdClass $property)
    {
        $type = $property->type . ($property->nullable ? '|null' : '');
        if ($property->type === 'enum') {
            $options = '';
            foreach ((array) $property->enum as $key => $value) {
                $options .= "$key: $value\n";
            }
            return Html::tag('span', ['title' => $options], $type);
        }

        return $type;
    }
}
