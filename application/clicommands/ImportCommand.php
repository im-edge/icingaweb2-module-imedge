<?php

namespace Icinga\Module\Imedge\CliCommands;

use gipfl\Log\Logger;
use gipfl\Log\Writer\WritableStreamWriter;
use Icinga\Cli\Command;
use Icinga\Module\Imedge\Controllers\DbTrait;
use IMEdge\Web\Data\ForeignModel\ZipCode;
use IMEdge\Web\Data\Importer\AutonomousSystemImporter;
use IMEdge\Web\Data\Importer\IpToCountryLiteImporter;
use IMEdge\Web\Data\Importer\MacAddressBlockImporter;
use React\Stream\WritableResourceStream;

class ImportCommand extends Command
{
    use DbTrait;

    /**
     * Import known ZIP codes from downloaded geonames.org files
     *
     * Usage
     * -----
     *
     *     icingacli inventory import zip --country de --file data/zip_codes/DE.txt
     */
    public function zipAction()
    {
        $countryCode = $this->params->getRequired('country');
        $fileName = $this->params->getRequired('file');
        $import = new ZipCode($this->db());
        $import->import($countryCode, ZipCode::parseGeoNamesFile($fileName));
    }

    /**
     * Import known MAC Address Block registrations from standards-oui.ieee.org
     *
     * Usage
     * -----
     *
     *     icingacli inventory import macvendor
     */
    public function macvendorAction()
    {
        // Hint: Code is not asynchronous, log lines will appear once everything has been completed
        $logger = new Logger();
        $logger->addWriter(new WritableStreamWriter(new WritableResourceStream(STDOUT)));
        $import = new MacAddressBlockImporter($this->db(), $logger);
        $import->refreshRegistrations();
    }

    public function asAction()
    {
        // Hint: Code is not asynchronous, log lines will appear once everything has been completed
        $logger = new Logger();
        $logger->addWriter(new WritableStreamWriter(new WritableResourceStream(STDOUT)));
        $import = new AutonomousSystemImporter($this->db(), $logger);
        $import->refreshRegistrations();
    }

    public function iprangeAction()
    {
        // Hint: Code is not asynchronous, log lines will appear once everything has been completed
        $logger = new Logger();
        $logger->addWriter(new WritableStreamWriter(new WritableResourceStream(STDOUT)));
        $import = new IpToCountryLiteImporter($this->db(), $logger);
        $import->refreshRegistrations();
    }
}
