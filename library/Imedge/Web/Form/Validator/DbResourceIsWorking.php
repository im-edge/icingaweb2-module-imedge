<?php

namespace Icinga\Module\Imedge\Web\Form\Validator;

use gipfl\Translation\TranslationHelper;
use Icinga\Data\ResourceFactory;
use ipl\Stdlib\Contract\Validator;
use Throwable;

class DbResourceIsWorking implements Validator
{
    use TranslationHelper;

    protected array $messages = [];

    public function isValid($value)
    {
        try {
            $resource = ResourceFactory::create($value);
            $db = $resource->getDbAdapter();
        } catch (Throwable $e) {
            $this->messages[] = sprintf($this->translate('Resource failed: %s'), $e->getMessage());
            return false;
        }

        try {
            $db->fetchOne('SELECT 1');
        } catch (Throwable $e) {
            $this->messages[] = sprintf(
                $this->translate('Could not connect to database: %s. Please make sure that your database exists and'
                    . ' your user has been granted enough permissions'),
                $e->getMessage()
            );
            return false;
        }

        return true;
    }

    public function getMessages()
    {
        return $this->messages;
    }
}
