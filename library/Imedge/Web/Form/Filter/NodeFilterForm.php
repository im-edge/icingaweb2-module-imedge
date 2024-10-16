<?php

namespace Icinga\Module\Imedge\Web\Form\Filter;

use gipfl\IcingaWeb2\Url;
use gipfl\Translation\TranslationHelper;
use gipfl\Web\Form;
use gipfl\ZfDb\Adapter\Pdo\PdoAdapter;
use IMEdge\Web\Data\RemoteLookup\DatanodeLookup;
use IMEdge\Web\Select2\FormElement\SelectRemoteElement;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

class NodeFilterForm extends Form
{
    use TranslationHelper;

    protected PdoAdapter $db;
    protected $defaultDecoratorClass = null;
    protected $method = 'GET';
    protected $useCsrf = false;
    protected $useFormName = false;

    public function __construct(PdoAdapter $db)
    {
        $this->db = $db;
    }

    protected function assemble()
    {
        $this->addElement(new SelectRemoteElement('node', [
            'placeholder'     => $this->translate('Edge Node'),
            'data-lookup-url' => Url::fromPath('imedge/lookup/node'),
            'lookup'          => new DatanodeLookup($this->db),
            'class'           => 'autosubmit',
        ]));
    }

    public function getUuid(): ?UuidInterface
    {
        if ($uuid = $this->getValue('node')) {
            return Uuid::fromString($uuid);
        }

        return null;
    }
}
