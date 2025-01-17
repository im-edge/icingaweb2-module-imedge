<?php

namespace Icinga\Module\Imedge\Web\Form;

use gipfl\Web\Form as gipflForm;
use gipfl\Web\Widget\Hint;
use Throwable;

class Form extends gipflForm
{
    protected function onError()
    {
        $messages = $this->getMessages();
        if (empty($messages)) {
            return;
        }
        $errors = [];
        foreach ($this->getMessages() as $message) {
            if ($message instanceof Throwable) {
                $errors[] = $message->getMessage();
            } else {
                $errors[] = $message;
            }
        }
        if (! empty($errors)) {
            $this->prepend(Hint::error(implode(', ', $errors)));
        }
    }
}
