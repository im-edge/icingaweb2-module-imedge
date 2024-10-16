<?php

namespace Icinga\Module\Imedge\Web\Table\Node;

use gipfl\DataType\Settings;
use gipfl\Translation\TranslationHelper;
use gipfl\Web\Table\NameValueTable;

class NodeInfoTable extends NameValueTable
{
    use TranslationHelper;

    protected $identifier;
    protected Settings $settings;

    public function __construct($identifier, Settings $settings)
    {
        $this->identifier = $identifier;
        $this->settings = $settings;
    }

    protected function assemble()
    {
        $settings = $this->settings;
        $this->addNameValuePairs([
            $this->translate('Node Name') => $this->identifier->name,
            $this->translate('UUID')      => $this->identifier->uuid,
            // $this->translate('DB Config') => Dsn::formatSettings($settings->getAsSettings('db')),
        ]);
    }
}
