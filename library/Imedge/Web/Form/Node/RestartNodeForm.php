<?php

namespace Icinga\Module\Imedge\Web\Form\Node;

use gipfl\IcingaWeb2\Icon;
use gipfl\Translation\TranslationHelper;
use gipfl\Web\Form\Feature\NextConfirmCancel;
use gipfl\Web\InlineForm;

class RestartNodeForm extends InlineForm
{
    use TranslationHelper;

    protected function assemble()
    {
        $this->add(Icon::create('arrows-cw'));
        (new NextConfirmCancel(
            NextConfirmCancel::buttonNext($this->translate('Restart')),
            NextConfirmCancel::buttonConfirm($this->translate('Really restart?')),
            NextConfirmCancel::buttonCancel($this->translate('Cancel'))
        ))->addToForm($this);
    }
}
