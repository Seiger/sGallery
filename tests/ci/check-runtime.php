<?php
declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';
if (Composer\InstalledVersions::isInstalled('spatie/image') || Composer\InstalledVersions::isInstalled('spatie/image-optimizer')) {
    throw new RuntimeException('The native CI fixture must not contain the legacy image packages.');
}
if (!method_exists(Seiger\sTask\sTask::class, 'heartbeat')) throw new RuntimeException('The fixture must use the pinned sTask heartbeat API.');
foreach ([Seiger\sGallery\sGallery::class, Seiger\sGallery\Services\GalleryFiles::class, Seiger\sGallery\Services\ImageSource::class] as $class) {
    $actual = (new ReflectionClass($class))->getFileName();
    if (!str_starts_with($actual, dirname(__DIR__, 2) . '/src/')) throw new RuntimeException("The fixture loaded an installed copy of $class");
}
echo "Current sGallery checkout, sTask heartbeat and native backend verified\n";
