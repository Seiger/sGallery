<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
$loaders = Composer\Autoload\ClassLoader::getRegisteredLoaders();
$composer = reset($loaders);
if (!$composer instanceof Composer\Autoload\ClassLoader) throw new RuntimeException('Expected a Composer loader.');
$authoritative = $composer->isClassMapAuthoritative();
$classes = [Seiger\sGallery\Services\ImageSource::class, Seiger\sGallery\Services\GalleryFiles::class];
foreach ($classes as $class) $composer->addClassMap([$class => __DIR__ . '/installed-copy-must-not-load.php']);
$composer->setClassMapAuthoritative(true);
try {
    foreach ($classes as $class) {
        $actual = (new ReflectionClass($class))->getFileName();
        $expected = realpath(dirname(__DIR__, 2) . '/src/Services/' . substr($class, strrpos($class, '\\') + 1) . '.php');
        if (realpath($actual) !== $expected) throw new RuntimeException("An installed classmap shadowed $class");
    }
    echo "PASS The current checkout wins over an authoritative installed classmap\n";
} finally {
    $composer->setClassMapAuthoritative($authoritative);
}
