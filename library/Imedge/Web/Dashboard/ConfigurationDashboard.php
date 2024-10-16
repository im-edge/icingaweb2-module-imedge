<?php

namespace Icinga\Module\Imedge\Web\Dashboard;

use gipfl\Translation\TranslationHelper;
use ipl\Html\BaseHtmlElement;
use ipl\Html\Html;

class ConfigurationDashboard extends BaseHtmlElement
{
    use TranslationHelper;

    protected $tag = 'div';
    protected WebActions $webActions;

    public function __construct(WebActions $webActions)
    {
        $this->webActions = $webActions;
    }

    /**
     * @throws \Icinga\Exception\NotFoundError
     */
    protected function assemble(): void
    {
        $this->addAttributes([
            'class' => 'action-dashboard',
            'data-base-target' =>'_main'
        ]);

        foreach ($this->webActions->getGroups() as $groupLabel => $actions) {
            $this->add(Html::tag('h1', $groupLabel));
            $this->add($ul = Html::tag('ul', [
                'class' => 'imedge-dashboard-actions',
                // 'data-base-target' => '_next',
            ]));

            foreach ($actions as $action) {
                $ul->add(new Dashlet(
                    $action->plural,
                    $action->listUrl,
                    $action->icon,
                    $action->description
                ));
            }
        }
    }
}
