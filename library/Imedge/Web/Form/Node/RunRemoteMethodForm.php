<?php

namespace Icinga\Module\Imedge\Web\Form\Node;

use gipfl\Web\Form\Element\Boolean;
use gipfl\Translation\TranslationHelper;
use IMEdge\Json\JsonString;
use IMEdge\Web\Rpc\Inspection\MetaDataMethod;
use IMEdge\Web\Rpc\Inspection\MetaDataParameter;
use ipl\Html\FormElement\NumberElement;
use ipl\Html\FormElement\TextareaElement;
use ipl\Html\FormElement\TextElement;
use gipfl\Web\Form;
use ipl\Html\Html;

class RunRemoteMethodForm extends Form
{
    use TranslationHelper;

    protected string $rpcMethod;
    protected MetaDataMethod $meta;
    protected array $arrayValues = [];
    protected array $settingsValues = [];

    public function __construct(string $rpcMethod, MetaDataMethod $method)
    {
        $this->rpcMethod = $rpcMethod;
        $this->meta = $method;
    }

    protected function assemble()
    {
        if ($this->meta->title) {
            $this->add(Html::tag('h2', $this->meta->title));
        }
        if ($this->meta->description) {
            $this->add(Html::tag('pre', $this->meta->description));
        }
        foreach ($this->meta->parameters as $param) {
            $this->addElement($this->createElementForRpcParameter($param));
        }

        $this->addElement('submit', 'submit', [
            'label' => $this->translate('Submit'),
        ]);
    }

    protected function createElementForRpcParameter(MetaDataParameter $param)
    {
        $name = $param->name;
        $attributes = [
            'label'       => $name,
            'description' => $param->description,
        ];
        switch ($param->type) {
            case 'string':
                return new TextElement($name, $attributes);
            case 'array':
                $this->arrayValues[$name] = true;
                return new TextareaElement($name, $attributes);
            case '\IMEdge\Config\Settings':
                $this->settingsValues[$name] = true;
                return new TextareaElement($name, $attributes);
            case 'int':
                return new NumberElement($name, $attributes);
            case 'bool':
            case 'boolean':
                return new Boolean($name, $attributes);
            default:
                return new TextareaElement($name, $attributes + ['data-element-class' => $param->type]);
                throw new \RuntimeException(\sprintf(
                    'I have no form element for the "%s" data type',
                    $param->getType()
                ));
        }
    }

    public function getNormalizedValues(): array
    {
        $values = $this->getValues();
        foreach ($values as $key => &$value) {
            if (isset($this->arrayValues[$key])) {
                $value = preg_split('/,\s*/', $value);
            } elseif (isset($this->settingsValues[$key])) {
                $value = JsonString::decode($value);
            }
        }

        return $values;
    }
}
