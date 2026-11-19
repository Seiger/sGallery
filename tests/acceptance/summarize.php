<?php
declare(strict_types=1);

$units = [];
foreach (file('/lab/results/status.tsv', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
    [$label, $driver, $scenario, $test, $code] = explode("\t", $line);
    if ($test === 'site') continue;
    $units[implode(':', [$label, $driver, $scenario, $test])] = (int) $code;
}
$summary = [
    'date' => '2026-11-19', 'package_commit' => '27bfc23', 'legacy_tag' => 'v1.8.1',
    'evolution' => '3.5.9', 'stask_commit' => '6355cf2',
    'runtime_script_runs' => count($units), 'runtime_failed' => array_filter($units, static fn ($code) => $code !== 0),
    'site_checks' => [],
];
foreach (glob('/lab/results/php*-acceptance-*-site.json') as $file) {
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
$summary['accepted'] = $summary['runtime_failed'] === [] && array_filter($summary['site_checks'], static fn ($cell) => $cell['failed'] !== []) === [];
file_put_contents('/lab/results/summary.json', json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n");
echo json_encode($summary, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
