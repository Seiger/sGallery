<?php
declare(strict_types=1);

// Configure only a disposable Evolution checkout; the package checkout stays read-only.
$options = getopt('', ['site:', 'package:', 'version:', 'spatie']);
$site = rtrim($options['site'] ?? '', '/');
if (!str_starts_with($site, '/lab/') || !is_file($site . '/core/composer.json')) {
    throw new RuntimeException('Use a disposable /lab/<site> Evolution checkout.');
}
$package = $options['package'] ?? '/pkg';
$version = $options['version'] ?? '2.x-dev';
$manifest = json_decode(file_get_contents($site . '/core/composer.json'), true, 512, JSON_THROW_ON_ERROR);
// composer-merge-plugin prefixes paths with custom/; keep absolute path repositories at the root.
$manifest['repositories'] = [
        ['type' => 'path', 'url' => $package, 'options' => ['symlink' => false, 'versions' => ['seiger/sgallery' => $version]]],
        ['type' => 'path', 'url' => '/stask', 'options' => ['symlink' => false, 'versions' => ['seiger/stask' => '2.2.x-dev']]],
];
file_put_contents($site . '/core/composer.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n");
$custom = [
    'require' => ['seiger/sgallery' => $version, 'seiger/stask' => '2.2.x-dev'],
];
if (array_key_exists('spatie', $options)) $custom['require']['spatie/image'] = '^3';
if (!is_dir($site . '/core/custom')) mkdir($site . '/core/custom', 0755, true);
file_put_contents($site . '/core/custom/composer.json', json_encode($custom, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n");
echo "Prepared $site for sGallery $version, Spatie " . (array_key_exists('spatie', $options) ? 'on' : 'off') . "\n";
