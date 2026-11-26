<?php
declare(strict_types=1);

use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository;
use Illuminate\Support\Facades\Facade;
use Seiger\sGallery\Builders\sGalleryBuilder;
use Seiger\sGallery\Services\ImageOptimizationQueue;
use Seiger\sTask\Models\sTaskModel;

function config($key, $default = null) { return $key === 'seiger.settings.sGallery.optimize' ? false : $default; }
function storage_path($path = '') { return EVO_BASE_PATH . 'storage/' . $path; }
function app($key = null) {
    $container = Illuminate\Container\Container::getInstance();
    return $key === null ? $container : $container->make($key);
}
require __DIR__ . '/bootstrap.php';
$strictHandler = set_error_handler(static function (int $severity, string $message, string $file, int $line) use (&$strictHandler): bool {
    // Record known PHP 8.6 dependency notices; keep package and other runtime warnings strict.
    $path = str_replace('\\', '/', $file);
    $known = (str_ends_with($path, '/vendor/illuminate/support/Str.php') && str_starts_with($message, 'Function mb_split() is deprecated since 8.6')) ||
        (str_contains($path, '/vendor/nesbot/carbon/src/') && str_starts_with($message, 'Function spl_object_hash() is deprecated since 8.6'));
    if ($severity === E_DEPRECATED && $known) {
        $GLOBALS['dedupDependencyNotices'][$message] = true;
        return true;
    }
    return $strictHandler($severity, $message, $file, $line);
});
printf("PHP %s\n", PHP_VERSION);
if (!method_exists(Seiger\sTask\sTask::class, 'heartbeat')) throw new RuntimeException('Use the current sTask with its heartbeat API.');
$root = sys_get_temp_dir() . '/sgallery-dedup-' . bin2hex(random_bytes(6)) . '/';
mkdir($root . 'storage/logs', 0700, true);
define('EVO_BASE_PATH', $root);
define('EVO_SITE_URL', 'https://images.test/');
$container = new Illuminate\Container\Container();
Illuminate\Container\Container::setInstance($container);
Facade::setFacadeApplication($container);
$container->instance('cache', new Repository(new ArrayStore()));
$container->instance('log', new class {
    /** Surface unexpected queue errors in the isolated database fixture. @since 1.9.0 */
    public function warning(string $message): void { throw new RuntimeException($message); }
});
$database = new Illuminate\Database\Capsule\Manager($container);
$database->addConnection(['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
$database->setAsGlobal();
$database->bootEloquent();
$database->schema()->create('s_workers', static function ($table): void {
    $table->increments('id');
    foreach (['identifier', 'scope', 'class'] as $name) $table->string($name);
    $table->boolean('active'); $table->integer('position'); $table->text('settings'); $table->boolean('hidden');
    $table->timestamps();
});
$database->schema()->create('s_tasks', static function ($table): void {
    $table->increments('id');
    foreach (['identifier', 'action', 'priority'] as $name) $table->string($name);
    $table->text('meta');
    foreach (['status', 'progress', 'attempts', 'max_attempts'] as $name) $table->integer($name);
    $table->integer('started_by')->nullable();
    $table->text('result')->nullable(); $table->text('message')->nullable();
    $table->timestamp('start_at')->nullable(); $table->timestamp('finished_at')->nullable(); $table->timestamps();
});
$service = (new ReflectionClass(Seiger\sTask\sTask::class))->newInstanceWithoutConstructor();
$container->instance('sTask', $service);
$queue = new ImageOptimizationQueue();
$descriptor = new ReflectionMethod(sGalleryBuilder::class, 'optimizationDescriptor');
$check = static function (bool $ok, string $label): void {
    if (!$ok) throw new RuntimeException($label);
    echo "PASS $label\n";
};
$make = static fn (int $side = 16): sGalleryBuilder => (new sGalleryBuilder())->file('source.png')->fit('max', $side)->format('png');
$create = static fn (array $job): sTaskModel => $service->create(ImageOptimizationQueue::IDENTIFIER, 'optimize', $job);
try {
    copy(__DIR__ . '/../fixtures/heif-rgba-source.png', $root . 'source.png');
    $job = $descriptor->invoke($make(), 'png');
    $first = $create($job)->fresh();
    $second = $create($job);
    $check($first->id === $second->id && sTaskModel::count() === 1, 'Default descriptor reuses the same task after real JSON persistence');
    $check($first->meta['params']['sharpen'] === 0, 'Default sharpening has the persisted integer type');
    $check(sTaskModel::normalizeMeta($job) === $first->meta, 'A descriptor matches the database metadata without lossy comparison');

    $integer = $descriptor->invoke($make()->sharpen(5.0), 'png');
    $intTask = $create($integer);
    $check($create($descriptor->invoke($make()->sharpen(5), 'png'))->id === $intTask->id && $intTask->fresh()->meta['params']['sharpen'] === 5,
        'Explicit integral float sharpening reuses the integer-valued task');
    $fraction = $descriptor->invoke($make()->sharpen(5.25), 'png');
    $fractionTask = $create($fraction)->fresh();
    $otherFraction = $create($descriptor->invoke($make()->sharpen(5.5), 'png'));
    $check($create($fraction)->id === $fractionTask->id && $otherFraction->id !== $fractionTask->id &&
        $fractionTask->meta['params']['sharpen'] === 5.25 && $otherFraction->fresh()->meta['params']['sharpen'] === 5.5,
        'Equal fractions deduplicate while distinct fractional strengths stay separate');
    $reordered = array_reverse($fraction, true);
    $reordered['params'] = array_reverse($reordered['params'], true);
    $check($create($reordered)->id === $fractionTask->id, 'sTask key normalization still handles reordered descriptor fields');

    $values = ImageOptimizationQueue::normalizeDescriptor(['values' => [0.0, 5.0, 5.25, '5', true, false, null, 0.000001, ['n' => -0.0]]])['values'];
    $check($values === [0, 5, 5.25, '5', true, false, null, 0.000001, ['n' => 0]],
        'JSON normalization preserves fractional values, strings, booleans, nulls and list order');

    // Seed the earlier descriptor form directly, before the new boundary normalization.
    $legacy = $descriptor->invoke($make(27), 'png');
    $legacy['params']['sharpen'] = 0.0;
    $active = $create($legacy);
    $active->update(['status' => sTaskModel::TASK_STATUS_RUNNING, 'attempts' => 2, 'max_attempts' => 7, 'progress' => 61, 'message' => 'in flight']);
    $rawMeta = $database->table('s_tasks')->where('id', $active->id)->value('meta');
    touch(storage_path('logs/sTask.heartbeat'));
    foreach (sTaskModel::activeStatuses() as $status) {
        $active->update(['status' => $status]);
        $before = sTaskModel::count();
        $check($queue->defer($legacy) && sTaskModel::count() === $before && $create($descriptor->invoke($make(27), 'png'))->id === $active->id,
            "Queued/preparing/running legacy status $status reuses its stored task");
        $active->refresh();
        $check($active->attempts === 2 && $active->max_attempts === 7 && $active->progress === 61 && $active->message === 'in flight' &&
            $database->table('s_tasks')->where('id', $active->id)->value('meta') === $rawMeta,
            'Deduplication preserves attempts, limits, progress, message and existing metadata');
    }
    $active->update(['status' => sTaskModel::TASK_STATUS_FINISHED]);
    $next = $create($descriptor->invoke($make(27), 'png'));
    $check($next->id !== $active->id && $next->attempts === 0 && $active->fresh()->attempts === 2,
        'A finished record does not suppress a new task or reset the earlier attempts');
    $next->update(['status' => sTaskModel::TASK_STATUS_FAILED, 'attempts' => 2, 'max_attempts' => 7, 'message' => 'retryable failure']);
    $count = sTaskModel::count();
    $check($queue->defer($legacy) && sTaskModel::count() === $count + 1, 'A failed task permits a new queued attempt');
    $retry = sTaskModel::latest('id')->first();
    $check($queue->defer($legacy) && sTaskModel::count() === $count + 1 && $retry->status === sTaskModel::TASK_STATUS_QUEUED && $retry->attempts === 0,
        'Repeated enqueue reuses the active retry even during its cooldown');
    $retry->update(['status' => sTaskModel::TASK_STATUS_FAILED]);
    $check(!$queue->defer($legacy) && sTaskModel::count() === $count + 1, 'Failed work respects the five-minute enqueue cooldown');
    foreach (glob(storage_path('sgallery/optimization/*.attempt')) as $file) touch($file, time() - 301);
    $check($queue->defer($legacy) && sTaskModel::count() === $count + 2 && $next->fresh()->attempts === 2 && $next->fresh()->max_attempts === 7,
        'Expired cooldown permits a new task without changing failed task counters');
    $check($queue->heavy($legacy), 'Equivalent float/integer metadata retains the same slow marker');

    $changed = $descriptor->invoke($make(28), 'png');
    $check($create($changed)->id !== $create($job)->id, 'Different geometry remains a distinct task');
    $changed = $job;
    $changed['source_version'][2] = str_repeat('0', 64);
    $check($create($changed)->id !== $first->id, 'Different source contents remain a distinct task');
    foreach (array_keys($GLOBALS['dedupDependencyNotices'] ?? []) as $notice) echo "DEPENDENCY DEPRECATED $notice\n";
} finally {
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($files as $file) $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
    rmdir($root);
}
