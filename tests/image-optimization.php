<?php
declare(strict_types=1);

namespace Seiger\sGallery\Builders {
    /** Controlled monotonic elapsed time for deterministic deadline tests. @since 1.6.0 */
    function microtime(bool $asFloat = false): float
    {
        $GLOBALS['clock'] += $GLOBALS['clock_step'];
        return $GLOBALS['clock'];
    }
}

namespace {
    $root = sys_get_temp_dir() . '/sgallery-test-' . bin2hex(random_bytes(6));
    mkdir($root . '/storage/logs', 0775, true);
    mkdir($root . '/assets/images', 0775, true);
    define('EVO_BASE_PATH', $root . '/');
    define('EVO_SITE_URL', 'https://example.test/');
    $GLOBALS['clock'] = 1000.0;
    $GLOBALS['clock_step'] = 0.0;
    $GLOBALS['budget'] = 5.0;
    function storage_path(string $path): string { return $GLOBALS['root'] . '/storage/' . $path; }
    function config($key, $default = null) {
        return match ($key) {
            'seiger.settings.sGallery.imageCacheDir' => 'derived/',
            'seiger.settings.sGallery.optimizationBudgetSeconds' => $GLOBALS['budget'],
            default => $default,
        };
    }
    function evo() {
        return new class {
            public function getConfig($key, $default = null) { return $default; }
            public function make($key, $parameters = []) { return \Illuminate\Container\Container::getInstance()->make($key, $parameters); }
            public function clearCache(): void { $GLOBALS['page_cache_cleared'] = true; }
        };
    }
    $autoload = getenv('SGALLERY_AUTOLOAD');
    if (!$autoload || !is_file($autoload)) { throw new RuntimeException('Set SGALLERY_AUTOLOAD to the site Composer autoload.php'); }
    $loader = require $autoload;
    $loader->setPsr4('Seiger\\sGallery\\', dirname(__DIR__) . '/src');
    $loader->addClassMap([\Seiger\sGallery\Builders\sGalleryBuilder::class => dirname(__DIR__) . '/src/Builders/sGalleryBuilder.php']);
    $container = new \Illuminate\Container\Container();
    \Illuminate\Container\Container::setInstance($container);
    \Illuminate\Support\Facades\Facade::setFacadeApplication($container);
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
        $table->text('result')->nullable(); $table->text('message')->nullable(); $table->timestamp('finished_at')->nullable(); $table->timestamps();
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
        imagejpeg($gd, $source); imagedestroy($gd);
        $builder = (new \Seiger\sGallery\Builders\sGalleryBuilder())->file('assets/images/test.jpg')->format('webp')->optimize(false);
        $url = $builder->getFile();
        $output = EVO_BASE_PATH . substr($url, strlen(EVO_SITE_URL));
        $check($url !== EVO_SITE_URL . 'assets/images/test.jpg' && is_file($output), 'Small image must complete synchronously');
        $check(getimagesize($output)['mime'] === 'image/webp', 'Real GD output must be a valid WebP');
        file_put_contents($output, '');
        $url = (new \Seiger\sGallery\Builders\sGalleryBuilder())->file('assets/images/test.jpg')->format('webp')->optimize(false)->getFile();
        clearstatcache();
        $check(filesize($output) > 0, 'Empty cache must regenerate');

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
        $check(!empty($GLOBALS['page_cache_cleared']), 'Worker must invalidate pages containing temporary originals');
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
        touch($source, time() + 60);
        try { (new \Seiger\sGallery\Builders\sGalleryBuilder())->processOptimization($job); throw new LogicException('Changed source accepted'); }
        catch (RuntimeException $error) { $check(str_contains($error->getMessage(), 'changed'), 'Queued source replacement must be rejected'); }
        $check(empty($GLOBALS['errors']), 'Unexpected image pipeline errors');
        echo "sGallery image optimization OK\n";
    } finally {
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($iterator as $item) { $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname()); }
        rmdir($root);
    }
}
