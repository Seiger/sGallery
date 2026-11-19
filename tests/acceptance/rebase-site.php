<?php
declare(strict_types=1);

[$script, $base, $target] = $argv;
if (!in_array($base, ['/lab/clean', '/lab/upgrade'], true) || !str_starts_with($target, '/lab/cases/')) {
    throw new RuntimeException('Rebase only disposable acceptance snapshots.');
}
foreach (['core/config/database/connections/default.php', 'core/.env', '.env'] as $relative) {
    $file = $target . '/' . $relative;
    if (is_file($file)) file_put_contents($file, str_replace($base . '/', $target . '/', file_get_contents($file)));
}
