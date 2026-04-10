<?php

namespace Icinga\Module\Imedge\Web\Table\Scenario;

use gipfl\IcingaWeb2\Link;
use gipfl\Translation\TranslationHelper;
use ipl\Html\Table;
use Ramsey\Uuid\UuidInterface;

class ScenariosTable extends Table
{
    use TranslationHelper;

    protected $defaultAttributes = [
        'class'            => 'common-table table-row-selectable',
        'data-base-target' => '_next',
    ];

    public function __construct(array $scenarios, UuidInterface $nodeUuid)
    {
        $this->getHeader()->add(Table::row([
            $this->translate('Scenario'),
            $this->translate('Interval'),
            $this->translate('Request Type'),
        ], null, 'th'));
        foreach ($scenarios as $uuid => $scenario) {
            $this->add([
                Link::create($scenario->name, 'imedge/snmp/scenario', [
                    'node'     => $nodeUuid->toString(),
                    'scenario' => $uuid,
                ]),
                $scenario->interval,
                $scenario->requestType,
            ]);
        }
    }
}
