<?php

namespace Icinga\Module\Imedge\Web\Form;

use gipfl\Translation\TranslationHelper;
use gipfl\Web\Form;
use gipfl\ZfDbStore\ZfDbStore;
use Icinga\Web\Notification;
use IMEdge\Web\Data\Model\UuidObject;
use ipl\Html\FormElement\SubmitElement;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

class UuidObjectForm extends Form
{
    use TranslationHelper;

    protected ?UuidInterface $uuid = null;
    protected bool $deleted = false;
    protected bool $isNew = false;
   /** @var class-string<UuidObject> */
    protected string $modelClass = 'NEEDS_TO_BE_OVERRIDDEN';
    protected ZfDbStore $store;
    protected UuidObject $instance;

    /** @var array|string */
    protected $keyProperty;

    public function __construct(ZfDbStore $store, ?UuidInterface $uuid = null)
    {
        $this->store = $store;
        if ($uuid) {
            $instance = $this->store->load($uuid->getBytes(), $this->modelClass);
            assert($instance instanceof UuidObject);
            $this->instance = $instance;
            $this->populate($instance->getProperties());
        } else {
            $this->isNew = true;
            $this->instance = new $this->modelClass();
        }
        $this->keyProperty = $this->instance->getKeyProperty();
    }

    public function populate($values)
    {
        if (isset($values[$this->keyProperty])) {
            $this->uuid = Uuid::fromBytes($values[$this->keyProperty]);
            unset($values[$this->keyProperty]);
        }
        foreach ($values as $key => &$value) {
            if ($value !== null && substr($key, -5) === '_uuid' && strlen($value) === 16) {
                $value = Uuid::fromBytes($value)->toString();
            }
        }

        return parent::populate($values);
    }

    protected function addButtons()
    {
        if ($this->uuid) {
            $this->addElement('submit', 'submit', [
                'label' => $this->translate('Store')
            ]);
            $this->addDeleteButton();
        } else {
            $this->addElement('submit', 'submit', [
                'label' => $this->translate('Create')
            ]);
        }
        $submit = $this->getElement('submit');
        assert($submit instanceof SubmitElement);
        $this->setSubmitButton($submit);
    }

    protected function addDeleteButton()
    {
        $button = $this->createElement('submit', 'delete', [
            'label' => $this->translate('Delete'),
            'formnovalidate' => true,
        ]);
        $submit = $this->getElement('submit');
        assert($submit instanceof SubmitElement);
        $decorator = $submit->getWrapper();
        assert($decorator instanceof Form\Decorator\DdDtDecorator);
        $dd = $decorator->dd();
        $dd->add($button);
        $this->registerElement($button);
        $label = $this->getObjectLabel();
        $labelReally = sprintf($this->translate('YES, I really want to delete %s'), $label);
        if ($button->hasBeenPressed()) {
            $dd->remove($button);
            $this->remove($button);
            $cancel = $this->createElement('submit', 'cancel', [
                'label' => $this->translate('Cancel'),
                'formnovalidate' => true,
            ]);
            $really = $this->createElement('submit', 'really_delete', [
                'label' => $labelReally,
                'formnovalidate' => true,
            ]);
            $this->registerElement($cancel);
            $this->registerElement($really);
            $dd->add([$cancel, $really]);
        }
        if ($this->getSentValue('really_delete') === $labelReally) {
            $this->store->delete($this->instance);
            $this->deleted = true;
            Notification::success(sprintf($this->translate('%s has been deleted'), $this->getObjectLabel()));
        }
    }

    protected function getObjectLabel()
    {
        if ($this->hasElement('label')) {
            return $this->getElementValue('label', $this->translate('A new object'));
        }

        return 'An object';
    }

    public function hasBeenDeleted(): bool
    {
        return $this->deleted;
    }

    public function getUuid(): ?UuidInterface
    {
        return $this->uuid;
    }

    public function onSuccess()
    {
        $this->succeedWithValues($this->getValues());
    }

    protected function succeedWithValues($values): void
    {
        $this->instance->setProperties($values);
        $this->uuid = $this->instance->getUuid(); // Generates a new one, if not set
        $result = $this->store->store($this->instance);
        if ($result === true) {
            if ($this->isNew) {
                Notification::success(sprintf($this->translate('%s has been created'), $this->getObjectLabel()));
            } else {
                Notification::success(sprintf(
                    $this->translate('%s has been modified'),
                    $this->getObjectLabel()
                ));
            }
        }
    }
}
