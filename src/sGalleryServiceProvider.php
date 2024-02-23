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
        // Only Manager
        if (IN_MANAGER_MODE) {
            // Add custom routes for package
            include(__DIR__.'/Http/routes.php');

            // Migration for create tables
            $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

            // Views
            $this->loadViewsFrom(__DIR__ . '/../views', 'sGallery');

            // MultiLang
            $this->loadTranslationsFrom(__DIR__.'/../lang', 'sGallery');

            // Files
            $this->publishes([
                dirname(__DIR__) . '/config/sGalleryAlias.php' => config_path('app/aliases/sGallery.php', true),
                dirname(__DIR__) . '/config/sGallerySettings.php' => config_path('seiger/settings/sGallery.php', true),
                dirname(__DIR__) . '/images/noimage.png' => public_path('assets/site/noimage.png'),
                dirname(__DIR__) . '/images/youtube-logo.png' => public_path('assets/site/youtube-logo.png'),
            ]);
        }

        // Check sMultisite
        $this->mergeConfigFrom(dirname(__DIR__) . '/config/sGalleryCheck.php', 'cms.settings');

        // Class alias
        $this->app->singleton(sGallery::class);
        $this->app->alias(sGallery::class, 'sGallery');
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