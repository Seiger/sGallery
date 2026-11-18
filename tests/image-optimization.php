<?php
declare(strict_types=1);

namespace Seiger\sGallery\Builders {
    /** Controlled monotonic elapsed time for deterministic deadline tests. @since 1.6.0 */
    function microtime(bool $asFloat = false): float
    {
        $GLOBALS['clock'] += $GLOBALS['clock_step'];
        return $GLOBALS['clock'];
    }

    /** Simulate an encoder reporting success while writing JPEG during the AVIF probe. @since 1.9.0 */
    function imageavif(\GdImage $image, $file): bool
    {
        return !empty($GLOBALS['mislabel_avif_probe']) ? \imagejpeg($image, $file) : \imageavif($image, $file);
    }

    /** Simulate a recoverable publication error after the derivative was encoded. @since 1.9.0 */
    function rename(string $source, string $destination): bool
    {
        if (!empty($GLOBALS['fail_image_publication']) && str_starts_with(basename($destination), 'test-19x19')) {
            $GLOBALS['failed_encoding_path'] = $source;
            throw new \RuntimeException('Simulated derivative publication failure.');
        }

        return \rename($source, $destination);
    }
}

namespace Seiger\sGallery\Services {
    /** Supply a deterministic memory reading without allocating memory in the test. @since 1.9.0 */
    function memory_get_usage(bool $realUsage = false): int
    {
        return $GLOBALS['estimated_memory_usage'] ?? \memory_get_usage($realUsage);
    }
}

namespace {
    use Illuminate\Cache\ArrayStore;
    use Illuminate\Cache\Repository;
    use Illuminate\Console\OutputStyle;
    use Seiger\sGallery\Services\GalleryCache;
    use Seiger\sGallery\Services\ImageMemoryBudget;
    use Seiger\sTask\Console\TaskWorker;
    use Seiger\sTask\Models\sTaskModel;
    use Symfony\Component\Console\Input\ArrayInput;
    use Symfony\Component\Console\Output\BufferedOutput;

    $root = sys_get_temp_dir() . '/sgallery-test-' . bin2hex(random_bytes(6));
    mkdir($root . '/storage/logs', 0775, true);
    mkdir($root . '/assets/images', 0775, true);
    define('EVO_BASE_PATH', $root . '/');
    define('EVO_SITE_URL', 'https://example.test/');
    $GLOBALS['clock'] = 1000.0;
    $GLOBALS['clock_step'] = 0.0;
    function storage_path(string $path): string { return $GLOBALS['root'] . '/storage/' . $path; }
    function app($key = null) {
        $container = \Illuminate\Container\Container::getInstance();
        return $key === null ? $container : $container->make($key);
    }
    function config($key, $default = null) {
        return match ($key) {
            'seiger.settings.sGallery.imageCacheDir' => 'derived/',
            'seiger.settings.sGallery.optimizationBudgetSeconds' => throw new RuntimeException('Optimization budget must not be read from config'),
            default => $default,
        };
    }
    function evo() {
        return new class {
            public function getConfig($key, $default = null) { return $default; }
            public function make($key, $parameters = []) { return \Illuminate\Container\Container::getInstance()->make($key, $parameters); }
            public function clearCache(): void { $GLOBALS['page_cache_clears'] = ($GLOBALS['page_cache_clears'] ?? 0) + 1; }
        };
    }
    $autoload = getenv('SGALLERY_AUTOLOAD');
    if (!$autoload || !is_file($autoload)) { throw new RuntimeException('Set SGALLERY_AUTOLOAD to the site Composer autoload.php'); }
    $loader = require $autoload;
    $loader->setPsr4('Seiger\\sGallery\\', dirname(__DIR__) . '/src');
    $loader->addClassMap([
        \Seiger\sGallery\Builders\sGalleryBuilder::class => dirname(__DIR__) . '/src/Builders/sGalleryBuilder.php',
        GalleryCache::class => dirname(__DIR__) . '/src/Services/GalleryCache.php',
        \Seiger\sGallery\Workers\ImageOptimizationWorker::class => dirname(__DIR__) . '/src/Workers/ImageOptimizationWorker.php',
    ]);
    $container = new \Illuminate\Container\Container();
    \Illuminate\Container\Container::setInstance($container);
    \Illuminate\Support\Facades\Facade::setFacadeApplication($container);
    $container->instance('cache', new Repository(new ArrayStore()));
    $container->instance('log', new class {
        public function warning($message): void { $GLOBALS['warnings'][] = $message; }
        public function error($message): void { $GLOBALS['errors'][] = $message; }
    });
    $capsule = new \Illuminate\Database\Capsule\Manager($container);
    $capsule->addConnection(['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
    $capsule->setAsGlobal();
    $capsule->bootEloquent();
    $capsule->schema()->create('s_workers', function ($table) {
        $table->increments('id');
        foreach (['identifier', 'scope', 'class'] as $name) { $table->string($name); }
        $table->boolean('active'); $table->integer('position'); $table->text('settings'); $table->boolean('hidden'); $table->timestamps();
    });
    $capsule->schema()->create('s_tasks', function ($table) {
        $table->increments('id');
        foreach (['identifier', 'action', 'priority'] as $name) { $table->string($name); }
        $table->text('meta'); $table->integer('status'); $table->integer('progress');
        $table->integer('started_by')->nullable(); $table->integer('attempts'); $table->integer('max_attempts');
        $table->text('result')->nullable(); $table->text('message')->nullable();
        $table->timestamp('start_at')->nullable(); $table->timestamp('finished_at')->nullable(); $table->timestamps();
    });
    $service = (new \ReflectionClass(\Seiger\sTask\sTask::class))->newInstanceWithoutConstructor();
    $container->instance('sTask', $service);
    $container->instance('translator', new class {
        public function get($key, $replace = [], $locale = null, $fallback = true): string { return $key; }
    });
    $check = static function (bool $condition, string $message): void {
        if (!$condition) { throw new RuntimeException($message); }
    };

    /** Controlled encoder for checking all quality candidates and partial publication. @since 1.6.0 */
    class TestImage extends \Spatie\Image\Image {
        public array $qualities = [];
        public bool $empty = false;
        public bool $fail = false;
        public function getWidth(): int { return 100; }
        public function getHeight(): int { return 100; }
        public function quality(int $quality): static { $this->qualities[] = $quality; return $this; }
        public function format(string $format): static { return $this; }
        public function save(string $path = ''): static {
            file_put_contents($path, $this->empty ? '' : str_repeat('x', 10000));
            if ($this->fail) { throw new RuntimeException('Encoder failed'); }
            return $this;
        }
    }
    try {
        $source = EVO_BASE_PATH . 'assets/images/test.jpg';
        $gd = imagecreatetruecolor(32, 32);
        imagejpeg($gd, $source); unset($gd);
        $builder = (new \Seiger\sGallery\Builders\sGalleryBuilder())->file('assets/images/test.jpg')->format('webp')->optimize(false);
        $url = $builder->getFile();
        $output = EVO_BASE_PATH . substr($url, strlen(EVO_SITE_URL));
        $check($url !== EVO_SITE_URL . 'assets/images/test.jpg' && is_file($output), 'Small image must complete synchronously');
        $check(getimagesize($output)['mime'] === 'image/webp', 'Real GD output must be a valid WebP');
        file_put_contents($output, '');
        $url = (new \Seiger\sGallery\Builders\sGalleryBuilder())->file('assets/images/test.jpg')->format('webp')->optimize(false)->getFile();
        clearstatcache();
        $check(filesize($output) > 0, 'Empty cache must regenerate');

        if (function_exists('imageavif') && (imagetypes() & IMG_AVIF) !== 0) {
            $requestAvif = static fn (): string => (new \Seiger\sGallery\Builders\sGalleryBuilder())
                ->file('assets/images/test.jpg')->resize(24, 24)->format('avif')->optimize(false)->getFile();
            $avifUrl = $requestAvif();
            $avifPath = EVO_BASE_PATH . substr($avifUrl, strlen(EVO_SITE_URL));
            $check(str_ends_with($avifUrl, '.avif') && (getimagesize($avifPath)[2] ?? null) === IMAGETYPE_AVIF,
                'Explicit AVIF must write actual AVIF when an encoder works');
            copy($source, $avifPath);
            $check($requestAvif() === $avifUrl && (getimagesize($avifPath)[2] ?? null) === IMAGETYPE_AVIF,
                'A cached JPEG with an AVIF filename must be regenerated');

            if (!extension_loaded('imagick')) {
                $driver = new \ReflectionProperty(\Seiger\sGallery\Builders\sGalleryBuilder::class, 'avifSupportDriver');
                $driver->setValue(null, null);
                $GLOBALS['mislabel_avif_probe'] = true;
                try {
                    $fallbackUrl = $requestAvif();
                    $fallbackPath = EVO_BASE_PATH . substr($fallbackUrl, strlen(EVO_SITE_URL));
                    $check(str_ends_with($fallbackUrl, '.webp') && (getimagesize($fallbackPath)[2] ?? null) === IMAGETYPE_WEBP,
                        'An encoder that reports success but emits JPEG must fall back to actual WebP');
                } finally {
                    $GLOBALS['mislabel_avif_probe'] = false;
                    $driver->setValue(null, null);
                }
            }
        }

        $save = new \ReflectionMethod(\Seiger\sGallery\Builders\sGalleryBuilder::class, 'saveWebpImage');
        $background = new \ReflectionProperty(\Seiger\sGallery\Builders\sGalleryBuilder::class, 'backgroundOptimization');
        $adaptive = (new \Seiger\sGallery\Builders\sGalleryBuilder())->file('assets/images/test.jpg');
        $background->setValue($adaptive, true);
        $image = new TestImage();
        $save->invoke($adaptive, $image, EVO_BASE_PATH . 'adaptive.webp');
        $check($image->qualities === [99,97,95,93,92,91,90,89,88,87,86,85,84,82,80], 'All 15 adaptive qualities must be retained');
        $explicit = (new \Seiger\sGallery\Builders\sGalleryBuilder())->file('assets/images/test.jpg')->quality(91);
        $background->setValue($explicit, true);
        $image = new TestImage();
        $save->invoke($explicit, $image, EVO_BASE_PATH . 'explicit.webp');
        $check($image->qualities === [91], 'Explicit quality must remain unchanged');
        $image->empty = true;
        try { $save->invoke($explicit, $image, EVO_BASE_PATH . 'empty.webp'); } catch (RuntimeException $error) {}
        $check(!file_exists(EVO_BASE_PATH . 'empty.webp'), 'Empty output must never publish');
        $image->empty = false; $image->fail = true;
        try { $save->invoke($explicit, $image, EVO_BASE_PATH . 'failed.webp'); } catch (RuntimeException $error) {}
        $check(!file_exists(EVO_BASE_PATH . 'failed.webp') && glob(EVO_BASE_PATH . '.sgallery-webp-*') === [], 'Failed encode must clean temporary files');

        touch(storage_path('logs/sTask.heartbeat'));
        $GLOBALS['clock_step'] = 10.0;
        $url = (new \Seiger\sGallery\Builders\sGalleryBuilder())->file('assets/images/test.jpg')->format('webp')->getFile();
        $check($url === EVO_SITE_URL . 'assets/images/test.jpg', 'Deadline must return the original');
        $check(\Seiger\sTask\Models\sTaskModel::count() === 1, 'Live heartbeat must enqueue exactly one task');
        $GLOBALS['clock_step'] = 0.0;
        $url = (new \Seiger\sGallery\Builders\sGalleryBuilder())->file('assets/images/test.jpg')->format('webp')->getFile();
        $check($url === EVO_SITE_URL . 'assets/images/test.jpg' && \Seiger\sTask\Models\sTaskModel::count() === 1, 'Known-heavy pending work must reuse its task');
        $task = \Seiger\sTask\Models\sTaskModel::first();
        $job = $task->meta;
        $deferred = (new \Seiger\sGallery\Builders\sGalleryBuilder())->file('assets/images/test.jpg');
        (new ReflectionProperty($deferred, 'optimizationStartedAt'))->setValue($deferred, $GLOBALS['clock']);
        $GLOBALS['clock_step'] = 2.0;
        $image = new TestImage();
        try { $save->invoke($deferred, $image, EVO_BASE_PATH . 'partial.webp'); }
        catch (\Seiger\sGallery\Exceptions\OptimizationDeferred $error) {}
        $check($image->qualities === [99,97] && !file_exists(EVO_BASE_PATH . 'partial.webp'), 'Deadline between attempts must not publish partial output');
        $GLOBALS['clock_step'] = 0.0;
        (new \Seiger\sGallery\Workers\ImageOptimizationWorker())->taskOptimize($task, $job);
        $task->refresh();
        $url = $task->result;
        $check($task->status === \Seiger\sTask\Models\sTaskModel::TASK_STATUS_FINISHED && $task->progress === 100, 'Worker must finalize successful optimization');
        $check($GLOBALS['page_cache_clears'] === 1, 'Worker must invalidate pages containing temporary originals');
        $check($url !== EVO_SITE_URL . 'assets/images/test.jpg', 'Background replay must publish a derived image');
        $url2 = (new \Seiger\sGallery\Builders\sGalleryBuilder())->file('assets/images/test.jpg')->format('webp')->getFile();
        $check($url === $url2, 'Next front request must reuse the completed cache');
        $fallbackOutput = EVO_BASE_PATH . substr($url, strlen(EVO_SITE_URL));
        file_put_contents($fallbackOutput, str_repeat("\0", 128), FILE_APPEND);
        (new ReflectionProperty(\Seiger\sGallery\Builders\sGalleryBuilder::class, 'avifSupportDriver'))->setValue(null, 'gd');
        $fallbackUrl = (new \Seiger\sGallery\Builders\sGalleryBuilder())->file('assets/images/test.jpg')->format('avif')->getFile();
        $check($fallbackUrl === $url, 'Published WebP fallback must be reused for a missing AVIF cache');

        $queue = new \Seiger\sGallery\Services\ImageOptimizationQueue();
        $check(!$queue->defer($job), 'Recently completed work must respect retry cooldown');
        foreach (glob(storage_path('sgallery/optimization/*.attempt')) as $attempt) { unlink($attempt); }
        $worker = \Seiger\sTask\Models\sWorker::first();
        $worker->update(['active' => false]);
        $check(!$queue->defer($job), 'An existing disabled worker must stay disabled');
        $worker->update(['active' => true]);
        touch(storage_path('logs/sTask.heartbeat'), time() - 3611);
        $check(!$queue->available(), 'Stale runner must disable queue delivery');
        $check(!$queue->defer($job), 'Stale heartbeat must not enqueue work');
        $GLOBALS['clock_step'] = 10.0;
        $staleUrl = (new \Seiger\sGallery\Builders\sGalleryBuilder())->file('assets/images/test.jpg')->format('png')->getFile();
        $check($staleUrl === EVO_SITE_URL . 'assets/images/test.jpg' && \Seiger\sTask\Models\sTaskModel::count() === 1, 'Stale runner must return original without queuing');
        $GLOBALS['clock_step'] = 0.0;
        $check(empty($GLOBALS['errors']), 'Unexpected image pipeline errors');

        $originalHash = hash_file('sha256', $source);
        $derivativeHash = hash_file('sha256', $fallbackOutput);
        $cacheDirectory = EVO_BASE_PATH . GalleryCache::directoryFor($job['file']);
        $cachedBefore = glob($cacheDirectory . '*.webp');
        $markersBefore = glob(EVO_BASE_PATH . 'derived/.sgallery-index/*.json');
        $failedJob = $job;
        $failedJob['params']['w'] = $failedJob['params']['h'] = 19;
        $nextJob = $job;
        $nextJob['params']['w'] = $nextJob['params']['h'] = 17;
        $failedTask = $service->create($task->identifier, $task->action, $failedJob);
        $nextTask = $service->create($task->identifier, $task->action, $nextJob);

        $runner = new TaskWorker();
        $runner->setOutput(new OutputStyle(new ArrayInput([]), new BufferedOutput()));
        $GLOBALS['fail_image_publication'] = true;
        try {
            $check($runner->handle() === TaskWorker::SUCCESS, 'sTask must finish its queue run after a publication error');
        } finally {
            unset($GLOBALS['fail_image_publication']);
        }
        $failedTask->refresh();
        $nextTask->refresh();
        $check($failedTask->status === sTaskModel::TASK_STATUS_FAILED && $failedTask->result === null &&
            str_contains($failedTask->message, 'did not publish'), 'sTask must report the publication error as a failed job');
        $check($nextTask->status === sTaskModel::TASK_STATUS_FINISHED && $nextTask->progress === 100, 'sTask must complete the next job in the same run');
        $nextOutput = EVO_BASE_PATH . substr($nextTask->result, strlen(EVO_SITE_URL));
        $check((getimagesize($nextOutput)['mime'] ?? null) === 'image/webp', 'Next job must publish a valid WebP');
        $check(isset($GLOBALS['failed_encoding_path']) && !is_file($GLOBALS['failed_encoding_path']) &&
            glob($cacheDirectory . '*.pending-*') === [] && glob($cacheDirectory . '.sgallery-webp-*') === [],
            'The renderer must remove temporary files after both attempts');
        $check(array_values(array_diff(glob($cacheDirectory . '*.webp'), $cachedBefore)) === [$nextOutput] &&
            count(glob(EVO_BASE_PATH . 'derived/.sgallery-index/*.json')) === count($markersBefore) + 1,
            'Only the successful job may publish and register a new derivative');
        $check(hash_file('sha256', $source) === $originalHash && hash_file('sha256', $fallbackOutput) === $derivativeHash,
            'A publication error must preserve the original and the existing cache');
        $check($GLOBALS['page_cache_clears'] === 2, 'Only successful jobs may invalidate the page cache');

        $wideSource = 'assets/images/wide.png';
        $wide = imagecreatetruecolor(8201, 4);
        imagepng($wide, EVO_BASE_PATH . $wideSource);
        unset($wide);
        $renderWide = static fn (int $width): string => (new \Seiger\sGallery\Builders\sGalleryBuilder())
            ->file($wideSource)->fit('stretch', $width, 4)->format('png')->optimize(false)->getFile();
        $readDimensions = static fn (string $url): array|false => getimagesize(EVO_BASE_PATH . substr($url, strlen(EVO_SITE_URL)));
        $wideUrl = $renderWide(8202);
        $check(array_slice($readDimensions($wideUrl), 0, 2) === [8202, 4], 'Old per-side limit must not reject a low-cost image');
        $memoryLimit = ini_get('memory_limit');
        ini_set('memory_limit', '128M');
        $GLOBALS['estimated_memory_usage'] = 112 * 1024 * 1024;
        $originalUrl = EVO_SITE_URL . $wideSource;
        try {
            touch(storage_path('logs/sTask.heartbeat'));
            $check($renderWide(8202) === $wideUrl, 'Existing derivatives must be served with low headroom');
            $check($renderWide(8203) === $originalUrl, 'Low web memory headroom must defer before encoding');
            $queued = sTaskModel::queued()->sole();
            $check($queued->meta['params']['w'] === 8203, 'Queued dimensions must retain the wide output width');
            try {
                (new \Seiger\sGallery\Builders\sGalleryBuilder())->processOptimization($queued->meta);
                throw new LogicException('An operation with insufficient worker headroom was accepted.');
            } catch (RuntimeException $error) {
                $check(str_contains($error->getMessage(), 'worker memory budget'), 'A worker with too little headroom must stop before decoding');
            }
            touch(storage_path('logs/sTask.heartbeat'), time() - 3611);
            $check($renderWide(8204) === $originalUrl && sTaskModel::queued()->count() === 1,
                'Low web headroom with sTask unavailable must return the original');
            ini_set('memory_limit', '768M');
            $check(ImageMemoryBudget::allows(24_000_001, 0, true),
                'The old pixel count must be accepted when the worker has enough memory');
            $check(!ImageMemoryBudget::allows((float) PHP_INT_MAX * PHP_INT_MAX, 0, true),
                'Dimension estimates must stay safe beyond the integer range');
            ini_set('memory_limit', '-1');
            $check(!ImageMemoryBudget::allows(1, 512 * 1024 * 1024, true), 'Unlimited CLI must use a finite memory estimate');
        } finally {
            unset($GLOBALS['estimated_memory_usage']);
            ini_set('memory_limit', $memoryLimit);
        }
        $check($runner->handle() === TaskWorker::SUCCESS, 'The sTask runner must replay the deferred wide image');
        $queued->refresh();
        $check($queued->status === sTaskModel::TASK_STATUS_FINISHED && array_slice($readDimensions($queued->result), 0, 2) === [8203, 4],
            'An image past the old side cap must reach the cache through sTask');

        touch($source, time() + 60);
        try { (new \Seiger\sGallery\Builders\sGalleryBuilder())->processOptimization($job); throw new LogicException('Changed source accepted'); }
        catch (RuntimeException $error) { $check(str_contains($error->getMessage(), 'changed'), 'Queued source replacement must be rejected'); }
        $check(count($GLOBALS['errors']) === 2 && str_contains($GLOBALS['errors'][0], 'Simulated derivative publication failure.') &&
            str_contains($GLOBALS['errors'][1], 'did not publish'), 'The Builder and sTask must log the simulated publication failure');
        echo "sGallery image optimization OK\n";
    } finally {
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($iterator as $item) { $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname()); }
        rmdir($root);
    }
}
