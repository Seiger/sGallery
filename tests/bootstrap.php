<?php

// Every fixture must exercise this checkout, including with an authoritative consumer classmap.
$autoload = getenv('PACKAGE_AUTOLOAD') ?: getenv('SGALLERY_AUTOLOAD') ?: dirname(__DIR__) . '/vendor/autoload.php';
if (!is_file($autoload)) {
    throw new RuntimeException('Set PACKAGE_AUTOLOAD or SGALLERY_AUTOLOAD to a Composer vendor/autoload.php');
}
$loader = require $autoload;
spl_autoload_register(static function (string $class): void {
    $prefix = 'Seiger\\sGallery\\';
    if (str_starts_with($class, $prefix)) {
        $file = dirname(__DIR__) . '/src/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
        if (is_file($file)) require $file;
    }
}, true, true);
