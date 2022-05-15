<?php namespace Seiger\sGallery;

use Generator;
use Illuminate\Support\Str;

trait PublishesMigrations
{
    /**
     * Publishes migrations as assets.
     *
     * @return void
     */
    protected function publishMigrations(): void
    {
        if ($this->app->runningInConsole()) {
            $generator = function(): Generator {
                foreach ($this->app->make('files')->allFiles(__DIR__ . '/../database/migrations') as $file) {
                    if ($file->getExtension() === 'php' && Str::startsWith($file->getFilename(), '0000_00_00_000000')) {
                        yield $file->getPathname() => $this->app->databasePath(
                            'migrations/' .
                            now()->format('Y_m_d_His') .
                            Str::after($file->getFilename(), '0000_00_00_000000')
                        );
                    }
                }
            };

            $this->publishes(iterator_to_array($generator()), 'migrations');
        }
    }
}