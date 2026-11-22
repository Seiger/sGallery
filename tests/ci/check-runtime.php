<?php
declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';
$backend = getenv('SGALLERY_TEST_BACKEND');
$spatie = class_exists(Spatie\Image\Image::class);
if (!in_array($backend, ['spatie', 'native'], true) || $spatie !== ($backend === 'spatie')) {
    throw new RuntimeException('Installed image packages do not match the selected CI backend.');
}
if ($backend === 'native' && Composer\InstalledVersions::isInstalled('spatie/image-optimizer')) {
    throw new RuntimeException('Native CI must not contain spatie/image-optimizer.');
}
if (!method_exists(Seiger\sTask\sTask::class, 'heartbeat')) throw new RuntimeException('The fixture must use the pinned sTask heartbeat API.');
foreach ([Seiger\sGallery\sGallery::class, Seiger\sGallery\Services\GalleryFiles::class, Seiger\sGallery\Services\ImageSource::class] as $class) {
    $actual = (new ReflectionClass($class))->getFileName();
    if (!str_starts_with($actual, dirname(__DIR__, 2) . '/src/')) throw new RuntimeException("The fixture loaded an installed copy of $class");
}
echo "Current sGallery checkout, sTask heartbeat and $backend backend verified\n";
