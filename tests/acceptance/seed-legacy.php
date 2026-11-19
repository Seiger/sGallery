<?php
declare(strict_types=1);

use Seiger\sGallery\Builders\sGalleryBuilder;
use Seiger\sGallery\Services\ImageOptimizationQueue;
use Seiger\sGallery\Workers\ImageOptimizationWorker;

require __DIR__ . '/bootstrap-site.php';
if (!class_exists(Spatie\Image\Image::class) || Composer\InstalledVersions::getPrettyVersion('seiger/sgallery') !== '1.8.1') {
    throw new RuntimeException('Seed using the installed sGallery 1.8.1 and Spatie dependency tree.');
}
$directory = EVO_BASE_PATH . 'assets/acceptance/';
if (!is_dir($directory)) mkdir($directory, 0755, true);
copy(__DIR__ . '/../fixtures/heif-rgba-source.png', $directory . 'source.png');
copy(__DIR__ . '/../fixtures/heif-rgba-source.png', $directory . 'mark.png');
$source = 'assets/acceptance/source.png';
$builder = static fn (): sGalleryBuilder => (new sGalleryBuilder())->file($source)->quality(90)->optimize(false);
$manifest = ['baseline' => 'v1.8.1', 'source' => $source, 'hash' => hash_file('sha256', $directory . 'source.png'), 'cache' => [], 'jobs' => []];
foreach (['png', 'webp'] as $format) {
    $url = $builder()->fit('max', 16, 16)->format($format)->getFile();
    $relative = rawurldecode(substr($url, strlen(EVO_SITE_URL)));
    if (!is_file(EVO_BASE_PATH . $relative) || $relative === $source) throw new RuntimeException('Old cache was not generated.');
    $manifest['cache'][$relative] = hash_file('sha256', EVO_BASE_PATH . $relative);
}
$service = evo()->make('sTask');
$service->registerWorker(ImageOptimizationWorker::class);
$service->getWorker(ImageOptimizationQueue::IDENTIFIER)->update(['active' => true]);
$descriptor = new ReflectionMethod(sGalleryBuilder::class, 'optimizationDescriptor');
$cases = [
    'fit' => $builder()->fit('max', 14, 14)->format('png'),
    'manual' => $builder()->manualCrop(8, 6, 2, 2)->format('png'),
    'focal' => $builder()->focalCrop(9, 7, 12, 8)->format('png'),
    'watermark' => $builder()->fit('max', 12, 12)->watermark('assets/acceptance/mark.png', ['width' => '2px'])->format('png'),
    'format-only' => (new sGalleryBuilder())->file($source)->format('webp')->optimize(false),
];
foreach ($cases as $name => $image) {
    $format = $name === 'format-only' ? 'webp' : 'png';
    $job = $descriptor->invoke($image, $format);
    $task = $service->create(ImageOptimizationQueue::IDENTIFIER, 'optimize', $job);
    $manifest['jobs'][$name] = ['id' => $task->id, 'meta' => $task->meta];
}
file_put_contents(EVO_BASE_PATH . 'acceptance-legacy.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
echo json_encode($manifest, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
