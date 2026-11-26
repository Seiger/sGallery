<?php
declare(strict_types=1);

// Only the disposable core checkout is changed; package requirements stay untouched.
$root = dirname(__DIR__, 2);
$core = $root . '/.test-core/core/composer.json';
$task = $root . '/.test-stask';
if (!is_file($core) || !is_file($task . '/composer.json')) {
    throw new RuntimeException('Check out .test-core and .test-stask.');
}
$manifest = json_decode(file_get_contents($core), true, 512, JSON_THROW_ON_ERROR);
if (($manifest['name'] ?? null) !== 'evolution-cms/evolution') throw new RuntimeException('Expected the isolated Evolution core manifest.');
$manifest['repositories'] = [
    ['type' => 'path', 'url' => $root, 'options' => ['symlink' => true, 'versions' => ['seiger/sgallery' => '2.x-dev']]],
    ['type' => 'path', 'url' => $task, 'options' => ['symlink' => true, 'versions' => ['seiger/stask' => '2.2.x-dev']]],
    ...($manifest['repositories'] ?? []),
];
$manifest['require']['seiger/sgallery'] = '2.x-dev';
$manifest['require']['seiger/stask'] = '2.2.x-dev';
file_put_contents($core, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n");
echo "Prepared Evolution fixture with native image processing\n";
