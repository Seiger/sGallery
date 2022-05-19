<?php namespace Seiger\sGallery\Facades;

use Illuminate\Support\Facades\Facade;

class sGallery extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'sGallery';
    }
}