<?php

namespace Icinga\Module\Imedge\Web\Form\Configuration;

use Exception;
use gipfl\IcingaWeb2\Link;
use gipfl\Translation\TranslationHelper;
use gipfl\Web\Widget\Hint;
use Icinga\Application\Config;
use Icinga\Data\ResourceFactory;
use Icinga\Exception\ConfigurationError;
use Icinga\Module\Imedge\Web\Form\Form;
use Icinga\Module\Imedge\Web\Form\Validator\DbResourceIsUtf8mb4Validator;
use Icinga\Module\Imedge\Web\Form\Validator\DbResourceIsWorking;
use Icinga\Web\Notification;
use ipl\Html\FormElement\SubmitElement;
use ipl\Html\Html;

class ChooseDbResourceForm extends Form
{
    use TranslationHelper;

    private Config $config;
    protected array $allowedDbTypes = ['mysql'];

    public function __construct(Config $config)
    {
        $this->config = $config;
    }

    protected function assemble()
    {
        $this->addResourceConfigElements();
    }

    protected function addResourceConfigElements()
    {
        $resources = $this->enumResources();

        $this->addElement('select', 'resource', [
            'label'         => $this->translate('DB Resource'),
            'multiOptions'  => [null => $this->translate('- please choose -')] + $resources,
            'class'         => 'autosubmit',
            'value'         => $this->config->get('db', 'resource'),
            'validators'    => [
                new DbResourceIsUtf8mb4Validator(),
                new DbResourceIsWorking(),
            ]
        ]);

        if (!$this->hasConfiguredResourceName()) {
            $this->prepend(Hint::info(Html::sprintf($this->translate(
                'No database resource has been configured yet. Please choose a'
                . ' resource to complete your config. A dedicated DB for IMEdge'
                . ' is highly recommended, please click %s to create new MySQL/MariaDB'
                . ' resource'
            ), Link::create(
                $this->translate('here'),
                'config/resource',
                null,
                ['data-base-target' => '_main']
            ))));
        }

        $this->addElement('submit', 'submit', [
            'label' => $this->translate('Store configuration')
        ]);
    }

    protected function hasConfiguredResourceName(): bool
    {
        return $this->config->get('db', 'resource') !== null;
    }

    protected function storeResourceConfig(): bool
    {
        $config = $this->config;
        $value = $this->getValue('resource');

        $config->setSection('db', ['resource' => $value]);

        try {
            $config->saveIni();
            // $this->setSuccessMessage
            Notification::success($this->translate('Configuration has been stored'));

            return true;
        } catch (Exception $e) {
            /** @var SubmitElement $submit */
            $submit = $this->getSubmitButton();
            $this->remove($submit);
            $this->add(Html::tag('dl', [
                Html::tag('dt', [
                    Html::tag('label', $this->translate('File content')),
                    Html::tag('br'),
                    Html::tag('small', $config->getConfigFile()),
                ]),
                Html::tag('dd', Html::tag('pre', null, (string) $config))
            ]));

            // Hint: re-adding the element shows two of them, and if
            // you clone it first it seems to be wrapped twice.
            $this->addElement('submit', 'submit', [
                'label' => $submit->getButtonLabel()
            ]);

            throw new ConfigurationError(sprintf(
                $this->translate(
                    'Unable to store the configuration to "%s". Please check'
                    . ' file permissions or manually store the content shown below'
                ),
                $config->getConfigFile()
            ));
        }
    }

    /**
     * @throws ConfigurationError
     */
    public function onSuccess()
    {
        $this->storeResourceConfig();
    }

    protected function enumResources(): array
    {
        $resources = [];

        foreach (ResourceFactory::getResourceConfigs() as $name => $resource) {
            if ($resource->get('type') === 'db' && in_array($resource->get('db'), $this->allowedDbTypes)) {
                $resources[$name] = $name;
            }
        }

        ksort($resources, SORT_LOCALE_STRING);

        return $resources;
    }
}
