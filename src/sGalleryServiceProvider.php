<?php namespace Seiger\sGallery;

require_once MODX_BASE_PATH . 'core/vendor/intervention/imagecache/src/Intervention/Image/ImageCacheController.php';
require_once MODX_BASE_PATH . 'core/vendor/intervention/image/src/Intervention/Image/Exception/ImageException.php';
require_once MODX_BASE_PATH . 'core/vendor/intervention/image/src/Intervention/Image/AbstractDriver.php';
require_once MODX_BASE_PATH . 'core/vendor/intervention/image/src/Intervention/Image/AbstractDecoder.php';
require_once MODX_BASE_PATH . 'core/vendor/intervention/image/src/Intervention/Image/AbstractEncoder.php';
require_once MODX_BASE_PATH . 'core/vendor/intervention/image/src/Intervention/Image/File.php';
require_once MODX_BASE_PATH . 'core/vendor/intervention/image/src/Intervention/Image/AbstractColor.php';

use EvolutionCMS\ServiceProvider;
use Event;

class sGalleryServiceProvider extends ServiceProvider
{
    protected $namespace = '';

    public function boot()
    {
        if (IN_MANAGER_MODE) {
            // Add custom routes for package
            include(__DIR__.'/Http/routes.php');

            // Migration for create tables
            $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

            // Views
            $this->loadViewsFrom(__DIR__ . '/../views', 'sGallery');

            // MultiLang
            $this->loadTranslationsFrom(__DIR__.'/../lang', 'sGallery');

            // For use config
            $this->publishes([
                __DIR__ . '/config/sgallery.php' => config_path('cms/settings/sgallery.php', true),
            ]);
        }

        // imagecache route
        /*if (is_string(config('imagecache.route'))) {

            $filename_pattern = '[ \w\\.\\/\\-\\@\(\)\=]+';

            // route to access template applied image file
            $this->app['router']->get(config('imagecache.route').'/{template}/{filename}', [
                'uses' => 'Intervention\Image\ImageCacheController@getResponse',
                'as' => 'imagecache'
            ])->where(['filename' => $filename_pattern]);
        }*/
    }

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        //this code work for add plugins to Evo
        $this->loadPluginsFrom(
            dirname(__DIR__) . '/plugins/'
        );
    }
}