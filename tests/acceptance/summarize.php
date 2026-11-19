<?php
declare(strict_types=1);

$run = $argv[1] ?? 'acceptance';
if (!preg_match('/^[a-zA-Z0-9_-]+$/D', $run)) throw new InvalidArgumentException('Use the matrix run suffix only.');
$labels = array_map(static fn (string $php): string => $php . '-' . $run, ['php84', 'php85', 'php86']);
$units = [];
foreach (file('/lab/results/status.tsv', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
    [$label, $driver, $scenario, $test, $code] = explode("\t", $line);
    if (!in_array($label, $labels, true)) continue;
    if ($test === 'site') continue;
    $units[implode(':', [$label, $driver, $scenario, $test])] = (int) $code;
}
$summary = [
    'date' => '2026-11-19', 'package_commit' => $argv[2] ?? '27bfc23', 'legacy_tag' => 'v1.8.1',
    'run_suffix' => $run,
    'evolution' => '3.5.9', 'stask_commit' => '6355cf2',
    'runtime_script_runs' => count($units), 'runtime_failed' => array_filter($units, static fn ($code) => $code !== 0),
    'site_checks' => [],
];
if (isset($argv[2])) {
    foreach (['src/Builders/sGalleryBuilder.php', 'src/Services/ImageOptimizationQueue.php'] as $path) {
        $summary['changed_production_sha256'][$path] = hash_file('sha256', dirname(__DIR__, 2) . '/' . $path);
    }
}
foreach (glob('/lab/results/php{84,85,86}-' . $run . '-*-site.json', GLOB_BRACE) as $file) {
    $report = json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);
    $capability = json_decode(file_get_contents(str_replace('-site.json', '-capabilities.json', $file)), true, 512, JSON_THROW_ON_ERROR);
    $summary['site_checks'][] = [
        'php' => $report['php'], 'driver' => $report['driver'], 'upgrade' => $report['upgrade'],
        'passed' => count(array_filter($report['results'], static fn ($result) => $result['ok'])),
        'failed' => array_keys(array_filter($report['results'], static fn ($result) => !$result['ok'])),
        'imagick' => $capability['imagick']['extension'] ?? null,
        'framework_spatie_packages' => $capability['framework_spatie_packages'],
    ];
}
if (count($summary['site_checks']) !== 24) throw new RuntimeException('Expected all 24 isolated site checks.');
if ($units === []) throw new RuntimeException('Expected runtime checks from the same run.');
$summary['accepted'] = $summary['runtime_failed'] === [] && array_filter($summary['site_checks'], static fn ($cell) => $cell['failed'] !== []) === [];
$output = '/lab/results/summary' . ($run === 'acceptance' ? '' : '-' . $run) . '.json';
file_put_contents($output, json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n");
echo json_encode($summary, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
