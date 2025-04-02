<?php

namespace Icinga\Module\Imedge\Web\Form\Measurement;

use gipfl\Translation\TranslationHelper;
use gipfl\Web\Form;
use gipfl\Web\Form\Decorator\DdDtDecorator;
use ipl\Html\FormElement\SubmitElement;

class DeleteUnreferencedFilesForm extends Form
{
    use TranslationHelper;

    public function __construct()
    {
    }

    protected function assemble()
    {
        $this->addElement('submit', 'submit', [
            'label' => $this->translate('Delete all unreferenced RRD files')
        ]);
        $cancel = $this->createElement('submit', 'cancel', [
            'label' => $this->translate('Cancel')
        ]);
        $this->registerElement($cancel);
        /** @var DdDtDecorator $wrapper */
        $wrapper = $this->getElement('submit')->getWrapper();
        $wrapper->dd()->add($cancel);
    }

    public function hasBeenCancelled(): bool
    {
        /** @var SubmitElement $element */
        $element = $this->getElement('cancel');
        return $element->hasBeenPressed();
    }
}
