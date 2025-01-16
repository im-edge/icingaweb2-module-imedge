<?php

namespace Icinga\Module\Imedge\Web\Dashboard;

class WebAction
{
    public ?string $name = null;
    public ?string $singular = null;
    public ?string $plural = null;
    public ?string $description = null;
    public ?string $table = null;
    public ?string $listUrl = null;
    public ?string $url = null;
    public ?string $icon = null;

    public static function create(array $properties): WebAction
    {
        // To be replaced with constructor with named parameters, once we require PHP 8.x
        $self = new WebAction();
        foreach ($properties as $key => $value) {
            $self->$key = $value;
        }

        return $self;
    }
}
