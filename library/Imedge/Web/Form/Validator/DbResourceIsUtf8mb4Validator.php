<?php

namespace Icinga\Module\Imedge\Web\Form\Validator;

use gipfl\Translation\TranslationHelper;
use Icinga\Data\ResourceFactory;
use ipl\Stdlib\Contract\Validator;
use Throwable;

class DbResourceIsUtf8mb4Validator implements Validator
{
    use TranslationHelper;

    protected array $messages = [];

    public function isValid($value)
    {
        try {
            $resourceConfig = ResourceFactory::getResourceConfig($value);
            if (
                !isset($resourceConfig->charset)
                || $resourceConfig->charset !== 'utf8mb4'
            ) {
                $this->messages[] = $this->translate('Please change the encoding for the database to utf8mb4');
                return false;
            }
            // $this->validate(); // Encoding Error does not appear, if we do not call this. But why?
        } catch (Throwable $e) {
            $this->messages[] = sprintf($this->translate('Resource failed: %s'), $e->getMessage());
            return false;
        }

        return true;
    }

    public function getMessages()
    {
        return $this->messages;
    }
}
