<?php

namespace Icinga\Module\Imedge\Web\Dashboard;

use gipfl\Translation\TranslationHelper;
use Icinga\Exception\NotFoundError;

class WebActions
{
    use TranslationHelper;

    protected array $actions;
    protected array $groups;

    public function __construct()
    {
        $this->init();
    }

    public function get(string $name): WebAction
    {
        if (! isset($this->actions[$name])) {
            throw new NotFoundError("'$name' not found");
        }

        return $this->actions[$name];
    }

    /**
     * @return array<string, WebAction[]>
     * @throws NotFoundError
     */
    public function getGroups(): array
    {
        $groups = [];
        foreach ($this->groups as $label => $keys) {
            if (! isset($groups[$label])) {
                $groups[$label] = [];
            }
            $current = &$groups[$label];
            foreach ($keys as $key) {
                $current[] = $this->get($key);
            }
        }

        return $groups;
    }

    public function init()
    {
        $this->groups = [
            $this->translate('Devices')   => ['snmpDevices', 'networkDevices', 'radioDevices'],
            $this->translate('Vendors, Models')   => ['vendors', 'deviceModels', 'rackModels'],
            $this->translate('Sites, Facilities') => ['sites', 'datanodes', 'snmpcredentials'],
            $this->translate('SNMP Discovery')   => ['snmpDiscoveryRules', 'snmpDiscoveryCandidates'],
            $this->translate('Insight')           => ['history'],
            $this->translate('Lookup Tables')       => ['dataSync' /*, 'dataOui', 'dataAs'*/],
            $this->translate('Remote Access, Integrations')       => ['apitokens'],
        ];
        $this->actions = [
            'vendors' => WebAction::create([
                'name'        => 'vendors',
                'singular'    => $this->translate('Vendor'),
                'plural'      => $this->translate('Vendors'),
                'description' => $this->translate('Manage your Hard-, and Software Vendors'),
                'table'   => 'input',
                // 'listUrl' => 'imedge/configuration/vendors',
                'listUrl' => '#',
                'url'     => 'imedge/configuration/vendor',
                'icon'    => 'book',
            ]),
            'deviceModels' => WebAction::create([
                'name'        => 'deviceModels',
                'singular'    => $this->translate('Device Model'),
                'plural'      => $this->translate('Device Models'),
                'description' => $this->translate('Device/Server model specifications: control size, weight and visualization'),
                'table'   => 'input',
                // 'listUrl' => 'imedge/configuration/server-models',
                'listUrl' => '#',
                'url'     => 'imedge/configuration/server-model',
                'icon'    => 'host',
            ]),
            'rackModels' => WebAction::create([
                'name'        => 'rackModels',
                'singular'    => $this->translate('Rack Model'),
                'plural'      => $this->translate('Rack Models'),
                'description' => $this->translate('Rack model specifications: define available units, airflow, doors'),
                'table'   => 'input',
                // 'listUrl' => 'imedge/configuration/rack-models',
                'listUrl' => '#',
                'url'     => 'imedge/configuration/rack-model',
                'icon'    => 'th-list',
            ]),
            'sites' => WebAction::create([
                'name'        => 'sites',
                'singular'    => $this->translate('Site'),
                'plural'      => $this->translate('Sites'),
                'description' => $this->translate('Offices, Plants, Datacenters, Ships, Cloud Providers and more'),
                'table'   => 'input',
                'listUrl' => 'imedge/sites',
                'url'     => 'imedge/site',
                'icon'    => 'sitemap',
            ]),
            'datanodes' => WebAction::create([
                'name'        => 'datanodes',
                'singular'    => $this->translate('Monitoring Node'),
                'plural'      => $this->translate('Monitoring Nodes'),
                'description' => $this->translate('Local and remote Monitoring Edge Nodes: Schedulers, Monitoring Satellites'),
                'table'   => 'input',
                'listUrl' => 'imedge/nodes',
                'url'     => 'imedge/node',
                'icon'    => 'globe',
            ]),
            'snmpDevices' => WebAction::create([
                'name'        => 'snmpDevices',
                'singular'    => $this->translate('SNMP Device'),
                'plural'      => $this->translate('SNMP Devices'),
                'description' => $this->translate('Routers, Switches, Antennas, Servers, Sensors - whatever you\'re monitoring via SNMP'),
                'table'   => 'snmp_agent',
                'listUrl' => 'imedge/snmp/devices',
                'url'     => 'imedge/snmp/device',
                'icon'    => 'barchart  ',
            ]),
            'networkDevices' => WebAction::create([
                'name'        => 'networkDevices',
                'singular'    => $this->translate('Network Device'),
                'plural'      => $this->translate('Network Devices'),
                'description' => $this->translate('Routers, Switches, all kinds of Network Devices'),
                'table'   => 'inventory_device',
                // 'listUrl' => 'imedge/network-devices',
                'listUrl' => '#',
                'url'     => 'imedge/network-device',
                'icon'    => 'sitemap',
            ]),
            'radioDevices' => WebAction::create([
                'name'        => 'radioDevices',
                'singular'    => $this->translate('Radio Device'),
                'plural'      => $this->translate('Radio Devices'),
                'description' => $this->translate('WiFi, WiMAX, Mobile and more'),
                'table'   => 'inventory_radio_device',
                // 'listUrl' => 'imedge/antennas',
                'listUrl' => '#',
                'url'     => 'imedge/antenna',
                'icon'    => 'wifi',
            ]),
            'snmpcredentials' => WebAction::create([
                'name'        => 'snmpcredentials',
                'singular'    => $this->translate('Credential'),
                'plural'      => $this->translate('Credentials'),
                'description' => $this->translate('Credentials: SNMP Community Strings, authentication and encryption keys'),
                'table'   => 'input',
                'listUrl' => 'imedge/snmp/credentials',
                'url'     => 'imedge/snmp/credentials',
                'icon'    => 'lock',
            ]),
            'history' => WebAction::create([
                'name'        => 'history',
                // 'singular'    => $this->translate('History'),
                'plural'      => $this->translate('Configuration History'),
                'description' => $this->translate('Change stream, as received and applied by Data Nodes'),
                'table'   => 'input',
                'listUrl' => 'imedge/history/table-sync',
                // 'url'     => 'imedge/snmp/credentials',
                'icon'    => 'history',
            ]),
            'snmpDiscoveryRules' => WebAction::create([
                'name'        => 'snmpDiscoveryRules',
                'singular'    => $this->translate('Discovery Rule'),
                'plural'      => $this->translate('Discovery Rules'),
                'description' => $this->translate('Define Discovery Rules, based on Imports (Icinga Director, NeDi, Files) or discovered neighbour devices'),
                'table'   => 'snmp_discovery_rule',
                'listUrl' => 'imedge/discovery/rules',
                'icon'    => 'sliders',
            ]),
            'snmpDiscoveryCandidates' => WebAction::create([
                'name'        => 'snmpDiscoveryCandidates',
                'singular'    => $this->translate('Discovery Candidate'),
                'plural'      => $this->translate('Discovery Candidates'),
                'description' => $this->translate('Discovery targets and their discovery state, by Node'),
                'table'   => 'snmp_discovery_candidate',
                'listUrl' => 'imedge/discovery/candidates',
                'icon'    => 'eye',
            ]),
            'dataSync' => WebAction::create([
                'name'        => 'dataSync',
                'singular'    => $this->translate('Data Synchronization'),
                'plural'      => $this->translate('Data Synchronizations'),
                'description' => $this->translate(
                    'Data Synchronization control and History'
                ),
                'listUrl' => '#',
                'icon'    => 'arrows-cw',
            ]),
            /*
            'dataOui' => WebAction::create([
                'name'        => 'dataSync',
                'singular'    => $this->translate('Data Synchronization'),
                'plural'      => $this->translate('Data Synchronizations'),
                'description' => $this->translate(
                    'Data Synchronization control and History'
                ),
                'listUrl' => '#',
                'icon'    => 'arrows-cw',
            ]),

            'dataOui', 'dataAs', ''
            */
            'apitokens' => WebAction::create([
                'name'        => 'apitokens',
                'singular'    => $this->translate('API Token'),
                'plural'      => $this->translate('Api Tokens'),
                'description' => $this->translate(
                    'Define API tokens with different permissions, allowing for automated'
                    . ' Inventory tasks'
                ),
                'table'   => 'api_token',
                'listUrl' => 'imedge/configuration/apitokens',
                'url'     => 'imedge/configuration/apitoken',
                'icon'    => 'lock-open-alt',
            ]),
        ];
    }
}
