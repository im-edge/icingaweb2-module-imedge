<?php

namespace Icinga\Module\Imedge\Discovery;

use Generator;
use gipfl\Translation\TranslationHelper;
use gipfl\Web\Form;
use IMEdge\Config\Settings;
use IMEdge\IpListGenerator\IpListGenerator;
use IMEdge\IpListGenerator\NediStyleSeedFileGenerator;

class SeedFileNediStyle extends DiscoveryRuleImplementation
{
    use TranslationHelper;

    protected const SETTING_NAME_SEED = 'seed';

    public function extendForm(Form $form): void
    {
        $form->addElement('textarea', self::SETTING_NAME_SEED, [
            'label'       => $this->translate('Seed File Content'),
            'description' => $this->translate('Syntax like in Nedi seed files, e.g. 192.0.0-2.2-254'),
        ]);
    }

    protected function prepareSettingsForDb(Settings $settings): void
    {
        $seed = $settings->getRequired(self::SETTING_NAME_SEED);
        $lines = [];
        foreach (preg_split('/\r?\n', $seed, -1, PREG_SPLIT_NO_EMPTY) as $line) {
            $lines[] = trim($line);
            // TODO: validate
        }

        $settings->set(self::SETTING_NAME_SEED, $lines);
    }

    protected function prepareSettingsForForm(Settings $settings): void
    {
        if ($seed = $settings->getArray(self::SETTING_NAME_SEED)) {
            $settings->set(self::SETTING_NAME_SEED, implode("\n", $seed));
        }
    }

    /**
     * @return class-string<IpListGenerator>
     */
    public function getTargetGeneratorClass(): string
    {
        return NediStyleSeedFileGenerator::class;
    }

    public function getCandidates(): Generator
    {
        $generator = new NediStyleSeedFileGenerator($this->settings);
        return $generator->generate();
    }
}
