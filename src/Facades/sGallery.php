<?php namespace Seiger\sGallery\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * Class sGallery
 *
 * This class extends the Facade class and represents the sGallery component.
 * @mixin \Seiger\sGallery\sGallery
 */
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