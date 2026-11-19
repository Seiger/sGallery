<?php
declare(strict_types=1);

use Composer\InstalledVersions;
use Illuminate\Console\OutputStyle;
use Illuminate\Support\Facades\Schema;
use Seiger\sGallery\Builders\sGalleryBuilder;
use Seiger\sGallery\Facades\sGallery;
use Seiger\sGallery\Services\ImageOptimizationQueue;
use Seiger\sGallery\Services\NativeImage;
use Seiger\sGallery\Workers\ImageOptimizationWorker;
use Seiger\sTask\Console\TaskWorker;
use Seiger\sTask\Models\sTaskModel;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

require __DIR__ . '/bootstrap-site.php';
$database = Illuminate\Support\Facades\DB::connection()->getDatabaseName();
if (realpath($database) !== realpath($site . '/core/database/acceptance.sqlite')) {
    throw new RuntimeException('The snapshot must use its own SQLite database: ' . $database);
}
$results = [];
$check = static function (string $name, bool $ok, mixed $detail = null) use (&$results): void {
    $results[$name] = ['ok' => $ok, 'detail' => $detail];
    echo ($ok ? 'PASS ' : 'FAIL ') . $name . ($detail === null ? '' : ' ' . json_encode($detail, JSON_UNESCAPED_SLASHES)) . "\n";
};
$check('no-spatie-image', !InstalledVersions::isInstalled('spatie/image') && !InstalledVersions::isInstalled('spatie/image-optimizer') && !class_exists(Spatie\Image\Image::class));
$check('package-provider-and-schema', evo()->bound('sGallery') && Schema::hasTable('s_galleries') && Schema::hasTable('s_tasks'));
config(['seiger.settings.sGallery.optimize' => false]);
$directory = EVO_BASE_PATH . 'assets/acceptance/';
if (!is_dir($directory)) mkdir($directory, 0755, true);
copy(__DIR__ . '/../fixtures/heif-rgba-source.png', $directory . 'current.png');
copy(__DIR__ . '/../fixtures/heif-rgba-source.png', $directory . 'mark.png');
$source = 'assets/acceptance/current.png';
$original = EVO_SITE_URL . $source;
$sourceHash = hash_file('sha256', $directory . 'current.png');
$path = static fn (string $url): string => EVO_BASE_PATH . rawurldecode(substr($url, strlen(EVO_SITE_URL)));
$png = NativeImage::canEncode('png', 'gd') || NativeImage::canEncode('png', 'imagick');
$make = static fn (): sGalleryBuilder => clone sGallery::file($source)->optimize(false);
$cases = [
    'resize-png-default-quality' => [$make()->resize(16, 16)->format('png'), 32, 16, 'png'],
    'resize-jpeg-default-quality' => [$make()->resize(16, 16)->format('jpeg'), 32, 16, 'jpeg'],
    'format-jpeg-default-quality' => [$make()->format('jpeg'), 32, 16, 'jpeg'],
    'format-webp-default-quality' => [$make()->format('webp'), 32, 16, 'webp'],
    'fit' => [$make()->fit('max', 16, 16)->format('png'), 16, 8, 'png'],
    'crop' => [$make()->crop('topLeft', 8, 6)->format('png'), 8, 6, 'png'],
    'manualCrop' => [$make()->manualCrop(2, 2, 8, 6)->format('png'), 8, 6, 'png'],
    'focalCrop' => [$make()->focalCrop(12, 8, 8, 6)->format('png'), 8, 6, 'png'],
    'watermark' => [$make()->fit('max', 16, 16)->watermark('assets/acceptance/mark.png', ['width' => '2px'])->format('png'), 16, 8, 'png'],
    'sharpen' => [$make()->fit('max', 16, 16)->sharpen(5)->format('png'), 16, 8, 'png'],
    'quality' => [$make()->fit('max', 16, 16)->quality(81)->format('png'), 16, 8, 'png'],
    'optimize' => [$make()->fit('max', 16, 16)->format('png')->optimize(), 16, 8, 'png'],
];
foreach ($cases as $name => [$image, $width, $height, $format]) {
    $url = $image->getFile();
    $canEncode = NativeImage::canEncode($format, 'gd') || NativeImage::canEncode($format, 'imagick');
    $info = str_starts_with($url, EVO_SITE_URL) ? @getimagesize($path($url)) : false;
    $check($name, $canEncode ? $url !== $original && $info !== false && array_slice($info, 0, 2) === [$width, $height] && $info['mime'] === 'image/' . $format : $url === $original, $url);
}
foreach (['jpeg', 'png', 'gif', 'webp', 'avif', 'bmp', 'tiff'] as $format) {
    $input = 'assets/acceptance-inputs/input.' . $format;
    if (!is_file(EVO_BASE_PATH . $input)) throw new RuntimeException('Run seed-inputs.php before taking matrix snapshots.');
    $readable = false;
    try {
        NativeImage::open($input, 'png')->save('assets/acceptance/decoder-probe.png');
        $readable = true;
    } catch (RuntimeException) {}
    $url = sGallery::file($input)->fit('max', 15, 15)->format('png')->quality(90)->optimize(false)->getFile();
    $check('input-' . $format, $readable
        ? $url !== EVO_SITE_URL . $input && array_slice(getimagesize($path($url)), 0, 3) === [15, 8, IMAGETYPE_PNG]
        : $url === EVO_SITE_URL . $input, ['readable' => $readable, 'url' => $url]);
}

$service = evo()->make('sTask');
$service->registerWorker(ImageOptimizationWorker::class);
$service->getWorker(ImageOptimizationQueue::IDENTIFIER)->update(['active' => true]);
$runner = new TaskWorker();
$runner->setOutput(new OutputStyle(new ArrayInput([]), new BufferedOutput()));
$manifestFile = EVO_BASE_PATH . 'acceptance-legacy.json';
if (is_file($manifestFile)) {
    $legacy = json_decode(file_get_contents($manifestFile), true, 512, JSON_THROW_ON_ERROR);
    $check('legacy-source-preserved', hash_file('sha256', EVO_BASE_PATH . $legacy['source']) === $legacy['hash']);
    foreach ($legacy['cache'] as $file => $hash) $check('legacy-cache-preserved:' . $file, is_file(EVO_BASE_PATH . $file) && hash_file('sha256', EVO_BASE_PATH . $file) === $hash);
    foreach ($legacy['jobs'] as $name => $job) {
        $task = sTaskModel::find($job['id']);
        $check('legacy-job-persisted:' . $name, $task !== null && $task->meta === $job['meta'] && $task->status === sTaskModel::TASK_STATUS_QUEUED);
    }
    $check('legacy-runner', $runner->handle() === TaskWorker::SUCCESS);
    foreach ($legacy['jobs'] as $name => $job) {
        $task = sTaskModel::findOrFail($job['id']);
        $expected = $png ? sTaskModel::TASK_STATUS_FINISHED : sTaskModel::TASK_STATUS_FAILED;
        $check('legacy-job-replayed:' . $name, $task->status === $expected && ($png ? is_file($path($task->result)) : $task->result === null), ['status' => $task->status, 'message' => $task->message]);
    }
}
$descriptor = new ReflectionMethod(sGalleryBuilder::class, 'optimizationDescriptor');
$image = $make()->fit('max', 13, 13)->format('png')->quality(90);
$job = $descriptor->invoke($image, 'png');
$invalid = $job;
unset($invalid['source_version']);
$failed = $service->create(ImageOptimizationQueue::IDENTIFIER, 'optimize', $invalid);
$queued = $service->create(ImageOptimizationQueue::IDENTIFIER, 'optimize', $job);
$duplicate = $service->create(ImageOptimizationQueue::IDENTIFIER, 'optimize', $job);
$check('queue-deduplicates', (int) $queued->id === (int) $duplicate->id, [$queued->id, $duplicate->id]);
$check('runner-continues', $runner->handle() === TaskWorker::SUCCESS);
$failed->refresh();
$queued->refresh();
$check('invalid-job-fails', $failed->status === sTaskModel::TASK_STATUS_FAILED && $failed->result === null);
$check('following-job-result', $queued->status === ($png ? sTaskModel::TASK_STATUS_FINISHED : sTaskModel::TASK_STATUS_FAILED) && ($png || $queued->result === null), $queued->message);
if ($png) {
    $check('worker-frontend-cache-identity', $queued->status === sTaskModel::TASK_STATUS_FINISHED && $image->getFile() === $queued->result);
    $cached = $make()->fit('max', 16, 16)->format('png');
    $url = $cached->getFile();
    $before = hash_file('sha256', $path($url));
    // Replace the source with a valid, opaque blue PNG of the same dimensions.
    $chunk = static fn (string $type, string $data): string => pack('N', strlen($data)) . $type . $data . pack('N', crc32($type . $data));
    $replacement = "\x89PNG\r\n\x1a\n" . $chunk('IHDR', pack('NNCCCCC', 32, 16, 8, 6, 0, 0, 0))
        . $chunk('IDAT', gzcompress(str_repeat("\0" . str_repeat("\x0a\x14\xf0\xff", 32), 16))) . $chunk('IEND', '');
    file_put_contents($directory . 'current.png', $replacement);
    touch($directory . 'current.png', time() + 60);
    $updated = $make()->fit('max', 16, 16)->format('png')->getFile();
    $check('source-replacement-refreshes-cache', $updated !== $original && hash_file('sha256', $path($updated)) !== $before, $updated);
} else {
    $check('no-codec-preserves-source', hash_file('sha256', $directory . 'current.png') === $sourceHash);
}
$report = ['php' => PHP_VERSION, 'driver' => getenv('SGALLERY_EXPECT_DRIVER'), 'site' => $site, 'upgrade' => is_file($manifestFile), 'results' => $results];
file_put_contents(EVO_BASE_PATH . 'acceptance-result.json', json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
exit(count(array_filter($results, static fn ($result) => !$result['ok'])) > 0 ? 1 : 0);
