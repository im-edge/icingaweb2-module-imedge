<?php

namespace Icinga\Module\Imedge\Discovery;

use Generator;
use gipfl\Web\Form;
use IMEdge\Config\Settings;

abstract class DiscoveryRuleImplementation
{
    protected Settings $settings;

    public function __construct(Settings $settings)
    {
        $this->settings = $settings;
    }
    abstract public function getCandidates(): Generator;

    public function getSettings(): Settings
    {
        return $this->settings;
    }

    protected function prepareSettingsForDb(Settings $settings): void
    {
    }

    protected function prepareSettingsForForm(Settings $settings): void
    {
    }

    public function extendForm(Form $form): void
    {
    }

    final public static function createInstance(
        string $implementation,
        Settings $settings
    ): DiscoveryRuleImplementation {
        if (! is_a($implementation, DiscoveryRuleImplementation::class, true)) {
            throw new \RuntimeException("$implementation is not a DiscoveryRuleImplementation");
        }
        $instance = new $implementation($settings);
        if (!$instance instanceof DiscoveryRuleImplementation) {
            throw new \RuntimeException("$implementation is not an instance of DiscoveryRuleImplementation");
        }

        return $instance;
    }
}
