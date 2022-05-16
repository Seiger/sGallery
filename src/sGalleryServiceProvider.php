<?php namespace Seiger\sGallery;

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
                __DIR__ . '/config/imagecache.php' => config_path('cms/settings/imagecache.php', true),
                __DIR__ . '/config/laravelimagecache.php' => EVO_CORE_PATH  . 'config/imagecache.php'
            ]);
        }
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