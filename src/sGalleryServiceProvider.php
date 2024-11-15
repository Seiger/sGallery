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
        // Load the package routes
        $this->app->router->middlewareGroup('mgr', config('app.middleware.mgr', []));
        $this->loadRoutesFrom(__DIR__.'/Http/routes.php');

        // Load migrations, views, translations only if necessary
        $this->loadMigrationsFrom(dirname(__DIR__) . '/database/migrations');
        $this->loadViewsFrom(dirname(__DIR__) . '/views', 'sGallery');
        $this->loadTranslationsFrom(dirname(__DIR__) . '/lang', 'sGallery');

        // Publish configuration and assets
        $this->publishResources();

        // Merge configuration for sGallery
        $this->mergeConfigFrom(dirname(__DIR__) . '/config/sGalleryCheck.php', 'cms.settings');

        // Register sGallery as a singleton using the key 'sGallery'
        $this->app->singleton('sGallery', fn($app) => new \Seiger\sGallery\sGallery());

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
        // Add plugins to Evolution CMS
        $this->loadPluginsFrom(dirname(__DIR__) . '/plugins/');
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