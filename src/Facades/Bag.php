<?php
namespace SamMcDonald\Bag\Facades;

use Illuminate\Support\Facades\Facade;

class Bag extends Facade {
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'bag';
    }
}
