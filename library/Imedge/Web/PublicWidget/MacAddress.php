<?php

namespace Icinga\Module\Imedge\Web\PublicWidget;

use Icinga\Module\Imedge\Db\DbFactory;
use IMEdge\Web\Data\Widget\MacAddress as MacAddressWidget;
use IMEdge\Web\Data\Helper\MacAddressHelper;
use IMEdge\Web\Data\Lookup\MacAddressBlockLookup;
use ipl\Html\BaseHtmlElement;
use ipl\Html\Html;
use ipl\Html\HtmlDocument;

class MacAddress
{
    protected static ?MacAddressBlockLookup $macLookup = null;

    public static function show(?string $macAddress): ?BaseHtmlElement
    {
        if ($macAddress === null) {
            return null;
        }
        return self::wrap(
            MacAddressWidget::parse(MacAddressHelper::toBinary($macAddress), self::lookup())
        );
    }

    public static function showBinary(?string $macAddress): ?BaseHtmlElement
    {
        if ($macAddress === null) {
            return null;
        }

        return self::wrap(
            MacAddressWidget::parse($macAddress, self::lookup())
        );
    }

    protected static function wrap(HtmlDocument $element): ?BaseHtmlElement
    {
        return Html::tag(
            'span',
            ['class' => ['icinga-module', 'module-inventory']],
            $element
        );
    }

    protected static function lookup(): MacAddressBlockLookup
    {
        return self::$macLookup ??= new MacAddressBlockLookup(DbFactory::db());
    }
}
