<?php

namespace Icinga\Module\Imedge\Web\Form;

use gipfl\Translation\TranslationHelper;
use gipfl\Web\Form;
use gipfl\ZfDbStore\NotFoundError;
use gipfl\ZfDbStore\ZfDbStore;
use Icinga\Authentication\Auth;
use Icinga\Module\Imedge\Auth\Permission;
use Icinga\Module\Imedge\Auth\TenantRestrictions;
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
    protected bool $allowDelete = false;
    protected ?UuidInterface $tenantUuid;

    public function __construct(ZfDbStore $store, ?UuidInterface $uuid = null, ?UuidInterface $tenantUuid = null)
    {
        $this->tenantUuid = $tenantUuid;
        $this->store = $store;
        if ($uuid) {
            $instance = $this->store->load($uuid->getBytes(), $this->modelClass);
            assert($instance instanceof UuidObject);
            if ($this->tenantUuid) {
                if ($instance->get('tenant_uuid') !== $this->tenantUuid->getBytes()) {
                    throw new NotFoundError();
                }
            }
            $this->instance = $instance;
            $this->populate($instance->getProperties());
            $this->uuid = $uuid;
        } else {
            $this->isNew = true;
            $this->instance = new $this->modelClass();
        }
        $this->keyProperty = $this->instance->getKeyProperty();
    }

    public function allowDelete(bool $allow = true): void
    {
        $this->allowDelete = $allow;
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
            if ($this->allowDelete) {
                $this->addDeleteButton();
            }
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
            try {
                $this->store->delete($this->instance);
                $this->deleted = true;
                Notification::success(sprintf($this->translate('"%s" has been deleted'), $this->getObjectLabel()));
            } catch (\Exception $e) {
                if (str_contains($e->getMessage(), 'Integrity constraint')) {
                    Notification::error(sprintf(
                        $this->translate('Failed to delete "%s", it seems to be still in use'),
                        $this->getObjectLabel()
                    ));
                } else {
                    Notification::error($e->getMessage());
                }
            }
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
                Notification::success(sprintf($this->translate('"%s" has been created'), $this->getObjectLabel()));
            } else {
                Notification::success(sprintf(
                    $this->translate('%s has been modified'),
                    $this->getObjectLabel()
                ));
            }
        }
    }

    protected function addTenantElement(): void
    {
        $this->addElement('select', 'tenant_uuid', [
            'label'    => $this->translate('Tenant'),
            'options'  => $this->enum('tenant'),
            'value'    => $this->getDefaultTenantUuidString(),
            'required' => !Auth::getInstance()->hasPermission(Permission::GLOBAL_ADMIN)
        ]);
    }

    protected function getDefaultTenantUuidString(): ?string
    {
        $uuid = $this->getDefaultTenantUuid();
        if ($uuid === null) {
            return null;
        }

        return $uuid->toString();
    }

    protected function getDefaultTenantUuid(): ?UuidInterface
    {
        // TODO: pick selected one from session
        return $this->tenantUuid ?: null;
    }

    protected function enum($table, $uuidColumn = 'uuid', $labelColumn = 'label'): array
    {
        $db = $this->store->getDb();
        $values = [];
        $select = $db->select()->from($table, [$uuidColumn, $labelColumn]);
        if ($table === 'tenant') {
            $select = TenantRestrictions::applyFilter($select, 'uuid');
        } elseif (! in_array($table, ['system_lifecycle', 'system_environment'])) {
            $select = TenantRestrictions::applyFilter($select);
        }
        foreach ($db->fetchPairs($select) as $uuid => $label) {
            $values[Uuid::fromBytes($uuid)->toString()] = $label;
        }

        return [null => $this->translate('- please choose -')] + $values;
    }
}
