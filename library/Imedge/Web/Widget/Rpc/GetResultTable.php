<?php

namespace Icinga\Module\Imedge\Web\Widget\Rpc;

use gipfl\Translation\TranslationHelper;
use gipfl\Web\Table\NameValueTable;
use Icinga\Module\Imedge\Snmp\VarBindList;
use ipl\Html\Html;
use ipl\Html\HtmlString;
use ipl\Html\Text;

class GetResultTable extends NameValueTable
{
    use TranslationHelper;
    /*
    // single Result:
     * success => true,
     * source => 192.0.10.1:123213
     * result =>
     *   errorStatus => 0
     *   errorIndex => 0
     *   varBinds =>
     *     0 =>
     *       0 => 1.3.6.1.2.1.1.5.0
     *       1 => { type => octet_string, value => "Some value"
     *  duration => 234323242
     */
    public function __construct(\stdClass $result)
    {
        if ($result->errorStatus > 0) {
            $this->addNameValueRow(
                Html::tag('span', ['class' => 'error'], $this->translate('Error')),
                sprintf('status = %s, index = %s', $result->errorStatus, $result->errorIndex)
            );
        }
        $varBinds = VarBindList::fromSerialization($result->varBinds);
        foreach ($varBinds->varBinds as $varBind) {
            $this->addNameValueRow($varBind->oid, new HtmlString(
                nl2br((new Text($varBind->value->getReadableValue()))->render())
            ));
        }
    }
}
