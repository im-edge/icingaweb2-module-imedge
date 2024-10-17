<?php

use Icinga\Application\Modules\Module;

/** @var Module $this */

$section = $this->menuSection('IMEdge')
    ->setUrl('imedge')
    ->setPriority(80)
    ->setIcon('services');

$this->provideJsFile('combined.js');
