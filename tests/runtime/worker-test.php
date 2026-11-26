<?php
declare(strict_types=1);

namespace Seiger\sGallery\Builders {
    /** Advance time without spending real CPU time in the frontend deadline checks. @since 1.9.0 */
    function microtime(bool $asFloat = false): float
    {
        $GLOBALS['workerClock'] += $GLOBALS['workerClockStep'];
        return $GLOBALS['workerClock'];
    }

    /** Fail one publication so the real sTask runner must recover and continue. @since 1.9.0 */
    function rename(string $source, string $destination): bool
    {
        if (!empty($GLOBALS['workerFailPublication']) && str_contains(basename($destination), '-19x19')) {
            throw new \RuntimeException('Simulated gallery publication failure.');
        }
        return \rename($source, $destination);
    }
}

namespace Seiger\sGallery\Services {
    /** Exercise memory deferral using a small source image. @since 1.9.0 */
    function memory_get_usage(bool $realUsage = false): int
    {
        return $GLOBALS['workerMemoryUsage'] ?? \memory_get_usage($realUsage);
    }
}

namespace {

use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository;
use Illuminate\Console\OutputStyle;
use Seiger\sGallery\Builders\sGalleryBuilder;
use Seiger\sGallery\Services\ImageAnimation;
use Seiger\sGallery\Services\ImageOptimizationQueue;
use Seiger\sGallery\Services\NativeImage;
use Seiger\sTask\Console\TaskWorker;
use Seiger\sTask\Models\sTaskModel;
use Seiger\sTask\Models\sWorker;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

/** Keep encoding predictable while testing queue scheduling. @since 1.9.0 */
function config($key, $default = null)
{
    return $key === 'seiger.settings.sGallery.optimize' ? false : $default;
}
/** Store queue markers inside the disposable fixture directory. @since 1.9.0 */
function storage_path($path = '') { return EVO_BASE_PATH . 'storage/' . $path; }
/** Resolve the services used by the real runner in the fixture container. @since 1.9.0 */
function app($key = null)
{
    $container = Illuminate\Container\Container::getInstance();
    return $key === null ? $container : $container->make($key);
}
/** Supply the CMS cache callback and service resolver needed by sTask. @since 1.9.0 */
function evo()
{
    return new class {
        /** Supply the configuration needed by this isolated worker. @since 1.9.0 */
        public function getConfig($key, $default = null) { return $default; }
        /** Resolve the real sTask worker services from the test container. @since 1.9.0 */
        public function make($key, $parameters = []) { return app()->make($key, $parameters); }
        /** Record page invalidation independently of the derivative cache. @since 1.9.0 */
        public function clearCache(): void { $GLOBALS['workerCacheClears']++; }
    };
}

require __DIR__ . '/bootstrap.php';
$strictHandler = set_error_handler(static function (int $severity, string $message, string $file, int $line) use (&$strictHandler): bool {
    // Installed CMS dependencies may emit PHP 8.6 notices while Eloquent builds queries.
    if ($severity === E_DEPRECATED && str_contains(str_replace('\\', '/', $file), '/vendor/')) {
        $GLOBALS['workerDependencyNotices'][$message] = true;
        return true;
    }
    return $strictHandler($severity, $message, $file, $line);
});
printf("PHP %s, Imagick %s\n", PHP_VERSION, extension_loaded('imagick') ? 'on' : 'off');
if (!class_exists(TaskWorker::class) || !method_exists(Seiger\sTask\sTask::class, 'heartbeat')) {
    throw new RuntimeException('Worker regression requires sTask with the heartbeat API.');
}
if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
    throw new RuntimeException('Worker regression requires PDO SQLite.');
}

$root = sys_get_temp_dir() . '/sgallery-worker-' . bin2hex(random_bytes(6)) . '/';
mkdir($root . 'storage/logs', 0700, true);
mkdir($root . 'assets', 0700);
define('EVO_BASE_PATH', $root);
define('EVO_SITE_URL', 'https://images.test/');
$GLOBALS['workerClock'] = 1000.0;
$GLOBALS['workerClockStep'] = 0.0;
$GLOBALS['workerCacheClears'] = 0;

$container = new Illuminate\Container\Container();
Illuminate\Container\Container::setInstance($container);
Illuminate\Support\Facades\Facade::setFacadeApplication($container);
$container->instance('cache', new Repository(new ArrayStore()));
$container->instance('log', new class {
    /** Collect expected queue and pipeline diagnostics. @since 1.9.0 */
    public function warning($message): void { $GLOBALS['workerWarnings'][] = $message; }
    /** Preserve the failure reason from the real runner. @since 1.9.0 */
    public function error($message): void { $GLOBALS['workerErrors'][] = $message; }
});
$container->instance('translator', new class {
    /** Return stable task labels without loading an application translator. @since 1.9.0 */
    public function get($key, $replace = [], $locale = null, $fallback = true): string { return $key; }
});
$database = new Illuminate\Database\Capsule\Manager($container);
$database->addConnection(['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
$database->setAsGlobal();
$database->bootEloquent();
$database->schema()->create('s_workers', static function ($table): void {
    $table->increments('id');
    foreach (['identifier', 'scope', 'class'] as $name) $table->string($name);
    $table->boolean('active');
    $table->integer('position');
    $table->text('settings');
    $table->boolean('hidden');
    $table->timestamps();
});
$database->schema()->create('s_tasks', static function ($table): void {
    $table->increments('id');
    foreach (['identifier', 'action', 'priority'] as $name) $table->string($name);
    $table->text('meta');
    $table->integer('status');
    $table->integer('progress');
    $table->integer('started_by')->nullable();
    $table->integer('attempts');
    $table->integer('max_attempts');
    $table->text('result')->nullable();
    $table->text('message')->nullable();
    $table->timestamp('start_at')->nullable();
    $table->timestamp('finished_at')->nullable();
    $table->timestamps();
});
$service = (new ReflectionClass(Seiger\sTask\sTask::class))->newInstanceWithoutConstructor();
$container->instance('sTask', $service);
$runner = new TaskWorker();
$runner->setOutput(new OutputStyle(new ArrayInput([]), new BufferedOutput()));
$check = static function (bool $ok, string $label): void {
    if (!$ok) throw new RuntimeException($label);
    echo "PASS $label\n";
};

try {
    $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAGAAAAAwCAYAAADuFn/PAAAAZElEQVR4nO3RsQ3AIADAMM7pYfy/MbcXMLGgxpFygceQJEmSJN3dms/r/QAAtAcAoD0AAO0BAGgPAEB7AADaAwDQHgCA9gAAtAcAoD0AAO0BAGgPAEB7AADaA/g7gCRJkiTprA+lqRk8WsX9mAAAAABJRU5ErkJggg==', true);
    file_put_contents($root . 'assets/source.png', $png);
    file_put_contents($root . 'assets/mark.png', $png);
    $sourceUrl = EVO_SITE_URL . 'assets/source.png';
    $path = static fn (string $url): string => $root . rawurldecode(substr($url, strlen(EVO_SITE_URL)));
    $build = static fn (int $side = 24): sGalleryBuilder => (new sGalleryBuilder())->file('assets/source.png')
        ->fit('max', $side, $side)->format('webp')->quality(90)->optimize(false);
    $descriptor = new ReflectionMethod(sGalleryBuilder::class, 'optimizationDescriptor');
    $queue = new ImageOptimizationQueue();
    touch(storage_path('logs/sTask.heartbeat'));
    $check($queue->available(), 'Queue availability depends on sTask and its heartbeat');
    $GLOBALS['workerClockStep'] = 10.0;
    $check($build()->getFile() === $sourceUrl && sTaskModel::queued()->count() === 1, 'Frontend deadline queues one job and returns the source');
    $GLOBALS['workerClockStep'] = 0.0;
    $check($build()->getFile() === $sourceUrl && sTaskModel::queued()->count() === 1, 'A slow marker reuses the active task without another render');
    $task = sTaskModel::queued()->sole();
    $job = $task->meta;
    $check($job['params']['fit'] === 'max' && $job['quality'] === 90 && $job['quality_explicit'] === true,
        'The queued descriptor stores scalar geometry and explicit quality');
    $check($runner->handle() === TaskWorker::SUCCESS, 'The real sTask runner completes its queue pass');
    $task->refresh();
    $check($task->status === sTaskModel::TASK_STATUS_FINISHED && $task->progress === 100 &&
        array_slice(getimagesize($path($task->result)), 0, 3) === [24, 12, IMAGETYPE_WEBP],
        'Worker publishes a verified derivative and marks the task finished');
    $check($build()->getFile() === $task->result && $GLOBALS['workerCacheClears'] === 1,
        'Frontend reuses the worker cache and successful publication invalidates page caches');
    $check(!$queue->defer($job), 'Completed work respects the enqueue retry cooldown');
    foreach (glob(storage_path('sgallery/optimization/*.attempt')) as $attempt) touch($attempt, time() - 301);
    $check($queue->defer($job) && $runner->handle() === TaskWorker::SUCCESS, 'A repeated job can complete after the cooldown');

    $worker = sWorker::sole();
    $worker->update(['active' => false]);
    $check(!$queue->defer($descriptor->invoke($build(22), 'webp')) && !$worker->fresh()->active, 'Deferral respects an existing disabled worker');
    $worker->update(['active' => true]);
    touch(storage_path('logs/sTask.heartbeat'), time() - 3611);
    $count = sTaskModel::count();
    $GLOBALS['workerClockStep'] = 10.0;
    $check(!$queue->available() && $build(23)->getFile() === $sourceUrl && sTaskModel::count() === $count,
        'A stale runner returns the source without enqueuing a task');
    $GLOBALS['workerClockStep'] = 0.0;
    touch(storage_path('logs/sTask.heartbeat'));

    $failed = $job;
    $failed['params']['w'] = $failed['params']['h'] = 19;
    $good = $job;
    $good['params']['w'] = $good['params']['h'] = 17;
    $failedTask = $service->create(ImageOptimizationQueue::IDENTIFIER, 'optimize', $failed);
    $goodTask = $service->create(ImageOptimizationQueue::IDENTIFIER, 'optimize', $good);
    $cachedHash = hash_file('sha256', $path($task->result));
    $clears = $GLOBALS['workerCacheClears'];
    $GLOBALS['workerFailPublication'] = true;
    try {
        $check($runner->handle() === TaskWorker::SUCCESS, 'A publication error does not stop the queue pass');
    } finally {
        unset($GLOBALS['workerFailPublication']);
    }
    $failedTask->refresh();
    $goodTask->refresh();
    $check($failedTask->status === sTaskModel::TASK_STATUS_FAILED && $failedTask->result === null && $goodTask->status === sTaskModel::TASK_STATUS_FINISHED,
        'The failed task is recorded and the following task finishes');
    $check($GLOBALS['workerCacheClears'] === $clears + 1 && hash_file('sha256', $path($task->result)) === $cachedHash,
        'A failed publication preserves the completed cache and only success invalidates pages');
    $check($queue->defer($failed) && $runner->handle() === TaskWorker::SUCCESS && sTaskModel::latest('id')->first()->status === sTaskModel::TASK_STATUS_FINISHED,
        'A recoverable publication failure can be retried successfully');

    $changed = $job;
    $changed['params']['w'] = $changed['params']['h'] = 11;
    $changedTask = $service->create(ImageOptimizationQueue::IDENTIFIER, 'optimize', $changed);
    touch($root . 'assets/source.png', time() + 60);
    $check($runner->handle() === TaskWorker::SUCCESS, 'Source replacement is handled by the queue runner');
    $changedTask->refresh();
    $check($changedTask->status === sTaskModel::TASK_STATUS_FAILED && str_contains($changedTask->message, 'source changed while queued'),
        'A descriptor queued before source replacement cannot publish stale output');

    $sourceMtime = filemtime($root . 'assets/source.png');
    $chunk = static fn (string $type, string $data): string => pack('N', strlen($data)) . $type . $data . pack('N', crc32($type . $data));
    $revision = static fn (string $sample): string => "\x89PNG\r\n\x1a\n" . $chunk('IHDR', pack('NNCCCCC', 96, 48, 8, 6, 0, 0, 0))
        . $chunk('IDAT', gzcompress(str_repeat("\0" . str_repeat($sample, 96), 48), 0)) . $chunk('IEND', '');
    file_put_contents($root . 'assets/source.png', $revision("\xe0\x14\x1e\xff"));
    touch($root . 'assets/source.png', $sourceMtime);
    $oldMeta = $descriptor->invoke($build(14), 'webp');
    $staleTask = $service->create(ImageOptimizationQueue::IDENTIFIER, 'optimize', $oldMeta);
    file_put_contents($root . 'assets/source.png', $revision("\x0a\x28\xe6\xff"));
    touch($root . 'assets/source.png', $sourceMtime);
    $freshMeta = $descriptor->invoke($build(14), 'webp');
    $freshTask = $service->create(ImageOptimizationQueue::IDENTIFIER, 'optimize', $freshMeta);
    $check($oldMeta['source_version'][0] === $freshMeta['source_version'][0] && $oldMeta['source_version'][1] === $freshMeta['source_version'][1],
        'Queued replacement keeps the earlier size and mtime');
    $check($runner->handle() === TaskWorker::SUCCESS, 'A stale content-hash job does not stop the queue');
    $staleTask->refresh(); $freshTask->refresh();
    $check($staleTask->status === sTaskModel::TASK_STATUS_FAILED && $staleTask->result === null &&
        str_contains($staleTask->message, 'source changed while queued') && $freshTask->status === sTaskModel::TASK_STATUS_FINISHED &&
        $build(14)->getFile() === $freshTask->result, 'The old revision fails and the following current revision publishes its own cache');
    file_put_contents($root . 'assets/source.png', $png);
    touch($root . 'assets/source.png', $sourceMtime);

    foreach (['crop', 'manual', 'focal', 'optimize', 'fractional'] as $mode) {
        $image = (new sGalleryBuilder())->file('assets/source.png')->format('png')->quality(83);
        $image = match ($mode) {
            'crop' => $image->crop('bottomRight', 20, 16)->sharpen(5)->watermark('assets/mark.png'),
            'manual' => $image->manualCrop(20, 16, 7, 4),
            'focal' => $image->focalCrop(20, 16, 35, 21),
            'optimize' => $image->optimize(),
            'fractional' => $image->fit('max', 21, 21)->sharpen(37.5),
        };
        $meta = $descriptor->invoke($image, 'png');
        $queued = $service->create(ImageOptimizationQueue::IDENTIFIER, 'optimize', $meta);
        $check($runner->handle() === TaskWorker::SUCCESS, "Runner replays $mode parameters");
        $queued->refresh();
        $front = $image->getFile();
        if ($mode === 'fractional') {
            $check($queued->meta['params']['sharpen'] === 37.5, 'JSON persistence and worker replay preserve fractional sharpening');
        }
        $check($queued->status === sTaskModel::TASK_STATUS_FINISHED && $front === $queued->result && (getimagesize($path($queued->result))[2] ?? null) === IMAGETYPE_PNG,
            "Queued $mode keeps the same derivative identity as the frontend");
    }

    $marked = (new sGalleryBuilder())->file('assets/source.png')->fit('stretch', 33, 21)->format('png')->watermark('assets/mark.png');
    $markedTask = $service->create(ImageOptimizationQueue::IDENTIFIER, 'optimize', $descriptor->invoke($marked, 'png'));
    file_put_contents($root . 'assets/mark.png', $png . 'changed watermark');
    $check($runner->handle() === TaskWorker::SUCCESS, 'A watermark replacement is handled by the runner');
    $markedTask->refresh();
    $check($markedTask->status === sTaskModel::TASK_STATUS_FAILED && $markedTask->result === null,
        'A watermark replaced while queued cannot publish under the earlier watermark hash');
    file_put_contents($root . 'assets/mark.png', $png);

    $gif = base64_decode('R0lGODlhDAAIAPAAAP8AAAAAACH/C05FVFNDQVBFMi4wAwEAAAAh+QQADwAAACwAAAAADAAIAAACCISPqcvtD2MqACH5BAAVAAAALAAAAAAMAAgAgAAA/wAAAAIIhI+py+0PYyoAOw==', true);
    file_put_contents($root . 'assets/animated.gif', $gif);
    $webp = base64_decode('UklGRsAAAABXRUJQVlA4WAoAAAACAAAACwAABwAAQU5JTQYAAAD/////AABBTk1GSAAAAAAAAAAAAAsAAAcAAJYAAAJWUDggMAAAANABAJ0BKgwACAACADQloAJ0ugH4AAOwAP7wxAv/ILlhdcjX/yA/5Af8gP/48gAAAEFOTUZEAAAAAAAAAAAACwAABwAA0gAAAFZQOCAsAAAAlAEAnQEqDAAIAAAANCWgAnS6AAOYAP75k2//kB//kB//kB//ID/iF3sgMAA=', true);
    file_put_contents($root . 'assets/animated.webp', $webp);
    if (NativeImage::canEncode('gif', 'imagick') && NativeImage::canEncode('webp', 'imagick')) {
        foreach (['gif' => IMAGETYPE_GIF, 'webp' => IMAGETYPE_WEBP] as $format => $type) {
            $animationSource = 'assets/animated.gif';
            $image = (new sGalleryBuilder())->file($animationSource)->fit('stretch', 6, 4)->format($format)
                ->quality(90)->optimize();
            $image->watermark('assets/mark.png', ['width' => '2px', 'padding' => 0]);
            $GLOBALS['workerClockStep'] = 10.0;
            $check($image->getFile() === EVO_SITE_URL . $animationSource && sTaskModel::queued()->count() === 1,
                "Frontend can defer a $format animation");
            $GLOBALS['workerClockStep'] = 0.0;
            $queued = sTaskModel::queued()->sole();
            $check($runner->handle() === TaskWorker::SUCCESS, "Runner processes a deferred $format animation");
            $queued->refresh();
            $front = $image->getFile();
            $timing = $queued->status === sTaskModel::TASK_STATUS_FINISHED ? ImageAnimation::timing($path($queued->result), $type) : null;
            $check($queued->status === sTaskModel::TASK_STATUS_FINISHED && $front === $queued->result && $timing === ['loops' => 0, 'delays' => [150, 210]],
                "Queued $format retains animation timing and frontend cache identity");
        }
    } else {
        $meta = $descriptor->invoke((new sGalleryBuilder())->file('assets/animated.gif')->format('gif'), 'gif');
        $unsupported = $service->create(ImageOptimizationQueue::IDENTIFIER, 'optimize', $meta);
        $check($runner->handle() === TaskWorker::SUCCESS, 'A missing animation codec is handled by the runner');
        $unsupported->refresh();
        $check($unsupported->status === sTaskModel::TASK_STATUS_FAILED && $unsupported->result === null,
            'The original animation is not reported as a completed derivative without its codec');
    }

    $legacy = $descriptor->invoke($build(27), 'webp');
    unset($legacy['quality_explicit'], $legacy['params']['fit'], $legacy['params']['w'], $legacy['params']['h']);
    $oldTask = $service->create(ImageOptimizationQueue::IDENTIFIER, 'optimize', $legacy);
    $check($runner->handle() === TaskWorker::SUCCESS, 'Runner accepts the earlier descriptor shape');
    $oldTask->refresh();
    $check($oldTask->status === sTaskModel::TASK_STATUS_FINISHED && array_slice(getimagesize($path($oldTask->result)), 0, 3) === [96, 48, IMAGETYPE_WEBP],
        'An earlier format-only job without the explicit-quality flag completes');

    $limit = ini_get('memory_limit');
    ini_set('memory_limit', '128M');
    $GLOBALS['workerMemoryUsage'] = 112 * 1024 * 1024;
    try {
        $check($build(29)->getFile() === $sourceUrl && sTaskModel::queued()->count() === 1, 'Low frontend headroom defers a small fixture before decoding');
    } finally {
        unset($GLOBALS['workerMemoryUsage']);
        ini_set('memory_limit', $limit);
    }
    $check($runner->handle() === TaskWorker::SUCCESS && sTaskModel::latest('id')->first()->status === sTaskModel::TASK_STATUS_FINISHED,
        'A job deferred for frontend memory completes with worker headroom');
    $check(file_get_contents($root . 'assets/source.png') === $png && glob($root . 'assets/cache/a/*pending*') === [],
        'Queue processing preserves the source and removes abandoned staged files');
    foreach (array_keys($GLOBALS['workerDependencyNotices'] ?? []) as $notice) {
        echo "DEPENDENCY DEPRECATED $notice\n";
    }
} finally {
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($files as $file) {
        $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
    }
    rmdir($root);
}
}
