<?php

namespace Icinga\Module\Imedge\Web\Dashboard;

use gipfl\IcingaWeb2\Icon;
use gipfl\IcingaWeb2\Link;
use ipl\Html\BaseHtmlElement;
use ipl\Html\Html;

class Dashlet extends BaseHtmlElement
{
    protected $tag = 'li';

    protected string $title;
    protected string $url;
    protected string $icon;
    protected ?string $summary;

    protected array $classes;

    public function __construct(
        string $title,
        string $url,
        ?string $icon = null,
        ?string $summary = null,
        ?array $classes = null
    ) {
        $this->title = $title;
        $this->url = $url;
        $this->icon = $icon ?? 'help';
        $this->summary = $summary;
        $this->classes = $classes ?? [];
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function getSummary(): ?string
    {
        return $this->summary;
    }

    protected function getIconName(): string
    {
        return $this->icon;
    }

    public function listCssClasses(): array
    {
        return $this->classes;
    }

    protected function assemble()
    {
        if ($this->getUrl() === '#') {
            $this->add(Html::tag('a', [
                'href' => 'javascript:void(0)',
                'class' => array_merge($this->listCssClasses() ?? [], ['disabled']),
            ], [
                $this->getTitle(),
                Icon::create($this->getIconName()),
                Html::tag('p', null, $this->getSummary()),
            ]));
        } else {
            $this->add(Link::create([
                $this->getTitle(),
                Icon::create($this->getIconName()),
                Html::tag('p', null, $this->getSummary())
            ], $this->getUrl(), null, [
                'class' => $this->listCssClasses()
            ]));
        }

        return parent::renderContent();
    }
}
