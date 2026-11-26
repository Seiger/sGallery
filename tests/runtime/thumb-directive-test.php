<?php
declare(strict_types=1);

use EvolutionCMS\AliasLoader;
use EvolutionCMS\Core;
use EvolutionCMS\Extensions\BladeCompiler;
use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Illuminate\Events\Dispatcher;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Facade;
use Seiger\sGallery\Builders\sGalleryBuilder;
use Seiger\sGallery\Providers\ThumbServiceProvider;
use Seiger\sGallery\sGalleryServiceProvider;

require __DIR__ . '/bootstrap.php';

$root = sys_get_temp_dir() . '/sgallery-blade-thumb-' . bin2hex(random_bytes(8)) . '/';
mkdir($root . 'assets/images', 0700, true);
define('EVO_BASE_PATH', $root);
define('EVO_SITE_URL', 'https://thumb.test/shop/');
define('EVO_CLASS', Core::class);
define('IN_MANAGER_MODE', false);
define('IN_INSTALL_MODE', false);
define('EVO_API_MODE', false);

$manifest = json_decode(file_get_contents(dirname(__DIR__, 2) . '/composer.json'), true, flags: JSON_THROW_ON_ERROR);
$core = new class ($root, $manifest['extra']['laravel']['providers']) extends Core {
    public function __construct(private string $fixture, array $providers)
    {
        $this->instance('path.config', $fixture . 'config');
        $this->instance('path.public', $fixture);
        $this->instance('path.storage', $fixture . 'storage');
        $this->instance('events', new Dispatcher($this));
        $this->instance('router', new Router($this['events'], $this));
        $this->instance('config', new Repository([
            'app' => [
                'providers' => ['Evolution_sGallery' => sGalleryServiceProvider::class] + $providers,
                'on_demand_providers' => ['Evolution_sGallery' => ['services' => ['sGallery']]],
            ],
            'seiger' => ['settings' => ['sGallery' => ['format' => 'png', 'optimize' => false]]],
        ]));
        $this->singleton('blade.compiler', fn () => new BladeCompiler(new Filesystem(), $fixture));
    }

    public function getConfig($name = '', $default = null)
    {
        return $default;
    }

    public function getCachedServicesPath()
    {
        return $this->fixture . 'providers.php';
    }

    protected function registersProvidersOnDemand(): bool
    {
        return true;
    }

    protected function onDemandRequestPath(): string
    {
        return 'article';
    }
};

$check = static function (bool $valid, string $label): void {
    if (!$valid) {
        throw new RuntimeException($label);
    }
    echo "PASS $label\n";
};
$path = static fn (string $url): string => $root . rawurldecode(substr($url, strlen(EVO_SITE_URL)));
$render = static function (BladeCompiler $blade, string $template, ?string $file) use ($root): string {
    file_put_contents($root . 'compiled.php', $blade->compileString($template));
    ob_start();
    try {
        require $root . 'compiled.php';
        return ob_get_contents();
    } finally {
        ob_end_clean();
    }
};

try {
    $GLOBALS['evo'] = $core;
    Container::setInstance($core);
    Facade::setFacadeApplication($core);
    AliasLoader::getInstance(['Event' => Illuminate\Support\Facades\Event::class])->register();
    $core->registerConfiguredProviders();
    $core->boot();
    $check(!$core->getProvider(sGalleryServiceProvider::class) && !$core->resolved('blade.compiler'),
        'Registering the directive leaves the gallery and Blade compiler lazy');

    $blade = $core->make('blade.compiler');
    $check(isset($blade->getCustomDirectives()['thumb']) && !$core->getProvider(sGalleryServiceProvider::class),
        'The cold Blade compiler knows @thumb before loading the gallery');

    $source = 'assets/images/café, photo.png';
    $image = imagecreatetruecolor(400, 240);
    imagefill($image, 0, 0, 0x337755);
    imagepng($image, $root . $source);
    $thumb = $render($blade, '@thumb($file)', $source);
    $check($core->getProvider(sGalleryServiceProvider::class) !== null &&
        str_starts_with($thumb, EVO_SITE_URL . 'assets/cache/images/sgallery/'),
        'Rendering the directive loads sGallery and returns a public thumbnail URL');
    $check(str_contains($thumb, '%C3%A9%2C%20') &&
        array_slice(getimagesize($path($thumb)), 0, 3) === [240, 120, IMAGETYPE_PNG],
        'The thumbnail has the native default geometry and an encoded filename');

    touch($path($thumb), 1700000000);
    $check($render($blade, "@thumb('assets/images/café, photo.png')", null) === $thumb &&
        filemtime($path($thumb)) === 1700000000, 'A literal filename and a variable reuse the same cache entry');
    $remote = 'https://images.invalid/photo.png?edition=1&size=large';
    $check($render($blade, '<img src="@thumb($file)">', $remote) === '<img src="' . e($remote) . '">',
        'The directive HTML-escapes the URL used in an image attribute');
    $check($render($blade, '@thumb($file)', null) === EVO_SITE_URL . 'assets/site/noimage.png',
        'A missing filename uses the package placeholder');

    $queued = 'assets/images/queued.png';
    copy($root . $source, $root . $queued);
    $builder = (new sGalleryBuilder())->file($queued)->fit('crop', 240, 120)->usePublicThumbnailCache();
    $descriptor = new ReflectionMethod(sGalleryBuilder::class, 'optimizationDescriptor');
    $job = json_decode(json_encode($descriptor->invoke($builder, 'png'), JSON_THROW_ON_ERROR), true, flags: JSON_THROW_ON_ERROR);
    $queuedThumb = (new sGalleryBuilder())->processOptimization($job);
    $check(str_starts_with($queuedThumb, EVO_SITE_URL . 'assets/cache/images/sgallery/') &&
        $queuedThumb === $render($blade, '@thumb($file)', $queued), 'A queued job publishes to the same public thumbnail cache');

    $core['config']->set('seiger.settings.sGallery.imageCacheDir', 'assets/derived/');
    $custom = $render($blade, '@thumb($file)', $source);
    $check(str_starts_with($custom, EVO_SITE_URL . 'assets/derived/') && is_file($path($custom)),
        'The directive honors a custom gallery cache directory');

    $customCompiler = new BladeCompiler(new Filesystem(), $root);
    $customCompiler->directive('thumb', static fn (): string => 'site thumbnail');
    $app = new Container();
    $app->instance('blade.compiler', $customCompiler);
    (new ThumbServiceProvider($app))->boot();
    $check($customCompiler->compileString('@thumb($file)') === 'site thumbnail',
        'An existing site directive keeps its definition');
} finally {
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($files as $file) {
        $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
    }
    rmdir($root);
}
