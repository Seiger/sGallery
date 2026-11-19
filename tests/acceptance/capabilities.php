<?php
declare(strict_types=1);

require getenv('PACKAGE_AUTOLOAD');
$profile = getenv('SGALLERY_EXPECT_DRIVER');
$gd = extension_loaded('gd');
$imagick = extension_loaded('imagick');
if ($gd !== in_array($profile, ['both', 'gd'], true) || $imagick !== in_array($profile, ['both', 'imagick'], true)) {
    throw new RuntimeException('The loaded image extensions do not match the selected profile.');
}
$spatie = array_values(array_filter(Composer\InstalledVersions::getInstalledPackages(), static fn ($name) => str_starts_with($name, 'spatie/')));
if (array_intersect($spatie, ['spatie/image', 'spatie/image-optimizer']) !== [] || class_exists(Spatie\Image\Image::class)) {
    throw new RuntimeException('The native acceptance dependency tree must not contain the Spatie image pipeline.');
}
echo json_encode([
    'php' => PHP_VERSION, 'zts' => PHP_ZTS, 'profile' => $profile, 'framework_spatie_packages' => $spatie,
    'evolution' => Composer\InstalledVersions::getRootPackage()['pretty_version'],
    'gd' => $gd ? gd_info() : null,
    'imagick' => $imagick ? ['extension' => phpversion('imagick'), 'library' => Imagick::getVersion(),
        'delegates' => Imagick::getConfigureOptions('DELEGATES'),
        'formats' => array_intersect(Imagick::queryFormats(), ['JPEG', 'PNG', 'GIF', 'WEBP', 'AVIF', 'HEIC', 'HEIF', 'JXL', 'BMP', 'TIFF'])] : null,
], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
