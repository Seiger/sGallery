<?php
/** Verify Evolution config loading and the provider fallback without duplicate reads. @since 1.7.0 */
$loader = require getenv('SGALLERY_AUTOLOAD');
$loader->setPsr4('Seiger\\sGallery\\', dirname(__DIR__) . '/src');
$repository = new \Illuminate\Config\Repository();
function config($key, $default = null) {
    global $repository;
    if (is_array($key)) { $repository->set($key); return; }
    return $repository->get($key, $default);
}
$root = sys_get_temp_dir() . '/sgallery-bootstrap-' . bin2hex(random_bytes(6));
$directory = $root . '/custom/config/seiger/settings';
mkdir($directory, 0755, true);
$file = $directory . '/sGallery.php';
$GLOBALS['sgallery_config_reads'] = 0;
file_put_contents($file, '<?php $GLOBALS["sgallery_config_reads"]++; return [7, "quality" => 83];');
$check = static function ($condition, $message) { if (!$condition) throw new RuntimeException($message); };
// Stop after the provider config step, before unrelated route/view registrations.
$app = new stdClass();
$app->router = new class {
    public function middlewareGroup($name, $middleware) { throw new LogicException('config-step-complete'); }
};
$provider = new \Seiger\sGallery\sGalleryServiceProvider($app);
$boot = static function () use ($provider) {
    try { $provider->boot(); }
    catch (LogicException $exception) {
        if ($exception->getMessage() !== 'config-step-complete') throw $exception;
    }
};
try {
    $core = (new ReflectionClass(\EvolutionCMS\Core::class))->newInstanceWithoutConstructor();
    $load = new ReflectionMethod(\EvolutionCMS\AbstractLaravel::class, 'loadConfiguration');
    $load->invoke($core, $repository, $root . '/custom/config');
    $check(config('seiger.settings.sGallery') === [7, 'quality' => 83], 'Evolution loads the custom config under the expected key');
    $boot();
    $check($GLOBALS['sgallery_config_reads'] === 1, 'Provider must not read the custom file again');
    $check(config('seiger.settings.sGallery') === [7, 'quality' => 83], 'Custom config remains unchanged without merging');
    config(['seiger.settings.sGallery' => []]);
    $boot();
    $check(config('seiger.settings.sGallery') === [], 'Explicit empty custom config remains empty');
    unlink($file);
    $repository = new \Illuminate\Config\Repository();
    $load->invoke($core, $repository, $root . '/custom/config');
    $boot();
    $check(config('seiger.settings.sGallery') === \Seiger\sGallery\Services\GallerySettings::defaults(), 'Missing custom file uses package defaults');
    echo "sGallery Evolution config bootstrap/fallback OK\n";
} finally {
    if (is_file($file)) unlink($file);
    foreach ([$directory, dirname($directory), dirname($directory, 2), dirname($directory, 3), $root] as $path) rmdir($path);
}
