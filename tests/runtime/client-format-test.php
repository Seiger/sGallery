<?php
declare(strict_types=1);

use Seiger\sGallery\Builders\sGalleryBuilder;

function config($key, $default = null) { return $default; }
require __DIR__ . '/bootstrap.php';
$root = sys_get_temp_dir() . '/sgallery-client-format-' . bin2hex(random_bytes(6)) . '/';
mkdir($root, 0700);
define('EVO_BASE_PATH', $root);
define('EVO_SITE_URL', 'https://images.test/');
$server = $_SERVER;
$cookies = $_COOKIE;
$session = $_SESSION ?? null;
$get = $_GET;
try {
    copy(__DIR__ . '/../fixtures/heif-rgba-source.png', $root . 'alpha.png');
    $chunk = static fn (string $type, string $data): string => pack('N', strlen($data)) . $type . $data . pack('N', crc32($type . $data));
    file_put_contents($root . 'opaque.png', "\x89PNG\r\n\x1a\n" . $chunk('IHDR', pack('NNCCCCC', 2, 1, 8, 2, 0, 0, 0))
        . $chunk('IDAT', gzcompress("\0\xff\0\0\0\0\xff")) . $chunk('IEND', ''));
    foreach ([
        ['alpha.png', 'image/webp,image/png', [], [], 'webp'],
        ['alpha.png', 'image/png', [], [], 'png'],
        ['alpha.png', 'image/jpeg', [], [], 'png'],
        ['opaque.png', 'image/jpeg', [], [], 'jpg'],
        ['opaque.png', '', [], [], 'jpg'],
        ['alpha.png', '', [], [], 'png'],
        ['alpha.png', 'image/webp', ['sgallery_supported_image_format' => 'png'], [], 'png'],
        ['alpha.png', 'image/webp', [], ['supported_image_format' => 'png'], 'png'],
        ['alpha.png', 'image/png', ['sgallery_supported_image_format' => 'not-an-image'], [], 'png'],
    ] as $index => [$source, $accept, $cookie, $stored, $expected]) {
        unset($_SERVER['HTTP_SEC_CH_ACCEPT'], $_SERVER['HTTP_USER_AGENT']);
        $_SERVER['HTTP_ACCEPT'] = $accept;
        $_COOKIE = $cookie;
        $_SESSION = $stored;
        $_GET = [];
        $format = (new sGalleryBuilder())->file($source)->getSupportedImageFormat();
        if ($format !== $expected) throw new RuntimeException("Client format $index: expected $expected, got $format");
        echo "PASS client format $index: $format\n";
    }
} finally {
    $_SERVER = $server;
    $_COOKIE = $cookies;
    $_GET = $get;
    if ($session === null) unset($_SESSION); else $_SESSION = $session;
    foreach (glob($root . '*') as $file) unlink($file);
    rmdir($root);
}
