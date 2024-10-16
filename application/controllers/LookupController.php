<?php

namespace Icinga\Module\Imedge\Controllers;

use gipfl\IcingaWeb2\CompatController;
use IMEdge\Web\Data\RemoteLookup\DatanodeLookup;
use IMEdge\Web\Data\RemoteLookup\Place;
use IMEdge\Web\Data\RemoteLookup\SnmpCredentialLookup;
use IMEdge\Web\Select2\BaseSelect2Lookup;

class LookupController extends CompatController
{
    use DbTrait;

    public function placeAction()
    {
        $this->lookup(Place::class);
    }

    public function datanodeAction()
    {
        $this->lookup(DatanodeLookup::class);
    }

    public function snmpCredentialAction()
    {
        $this->lookup(SnmpCredentialLookup::class);
    }

    protected function lookup($class)
    {
        /** @var BaseSelect2Lookup $lookup */
        $lookup = new $class($this->db(), $this->params->get('search'), $this->params->get('page'));
        $this->sendJson($lookup->getResponse());
    }

    protected function sendJson($body)
    {
        header('Content-Type: application/json');
        echo json_encode($body);
        exit; // TODO: nice shutdown
    }
}
