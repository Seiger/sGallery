<?php namespace Seiger\sGallery;

use EvolutionCMS\ServiceProvider;
use Event;

class sGalleryServiceProvider extends ServiceProvider
{
    protected $namespace = '';

    public function boot()
    {
        if (IN_MANAGER_MODE) {
            //Add custom routes for package
            include(__DIR__.'/Http/routes.php');

            //Migration for create tables
            //$this->loadMigrationsFrom(__DIR__ . '/../database/migrations'); 2022_05_15_185152_create_s_gallery_table.php
            if ($this->app->runningInConsole()) {
                // Export the migration
                if (!class_exists('CreateSGalleryTable')) {
                    $this->publishes([
                        __DIR__ . '/../database/migrations/create_s_gallery_table.php.stub' => database_path('migrations/' . date('Y_m_d_His', time()) . 'create_s_gallery_table.php'),
                        // you can add any number of migrations here
                    ], 'migrations');
                }
            }

            //Views
            $this->loadViewsFrom(__DIR__ . '/../views', 'sGallery');

            //MultiLang
            $this->loadTranslationsFrom(__DIR__.'/../lang', 'sGallery');
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