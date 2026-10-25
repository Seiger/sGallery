<?php

// Load a caller-supplied Evolution dependency tree without coupling tests to a site.
$autoload = getenv('PACKAGE_AUTOLOAD') ?: dirname(__DIR__, 2) . '/vendor/autoload.php';
if (!is_file($autoload)) {
    throw new RuntimeException('Set PACKAGE_AUTOLOAD to an Evolution CMS core vendor/autoload.php');
}
require $autoload;
spl_autoload_register(static function (string $class): void {
    $prefix = 'Seiger\sGallery\\';
    if (str_starts_with($class, $prefix)) {
        $file = dirname(__DIR__, 2) . '/src/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
        if (is_file($file)) {
            require $file;
        }
    }
}, true, true);
// Make warnings and PHP-version deprecations visible as regression failures.
set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
    if (!(error_reporting() & $severity)) {
        return false;
    }
    throw new ErrorException($message, 0, $severity, $file, $line);
});
