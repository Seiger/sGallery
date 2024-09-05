<?php namespace Seiger\sGallery;

use EvolutionCMS\ServiceProvider;
use Event;

class sGalleryServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     *
     * @return void
     */
    public function boot()
    {
        // Only load these resources in Manager mode
        if (IN_MANAGER_MODE) {
            // Add custom routes for package
            include(__DIR__.'/Http/routes.php');

            // Load migrations, views, translations only if necessary
            $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
            $this->loadViewsFrom(__DIR__ . '/../views', 'sGallery');
            $this->loadTranslationsFrom(__DIR__.'/../lang', 'sGallery');

            // Publish configuration and assets
            $this->publishResources();
        }

        // Merge configuration for sGallery
        $this->mergeConfigFrom(dirname(__DIR__) . '/config/sGalleryCheck.php', 'cms.settings');

        // Register sGallery as a singleton using the key 'sGallery'
        $this->app->singleton('sGallery', function ($app) {
            return new \Seiger\sGallery\sGallery();
        });

        // Create class alias for the facade
        class_alias(\Seiger\sGallery\Facades\sGallery::class, 'sGallery');
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

    /**
     * Publish the necessary resources for the package.
     *
     * @return void
     */
    protected function publishResources()
    {
        $this->publishes([
            dirname(__DIR__) . '/config/sGallerySettings.php' => config_path('seiger/settings/sGallery.php', true),
            dirname(__DIR__) . '/images/noimage.png' => public_path('assets/site/noimage.png'),
            dirname(__DIR__) . '/images/youtube-logo.png' => public_path('assets/site/youtube-logo.png'),
        ]);
    }
}