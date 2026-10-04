<?php

/**
 * Requires complete executable-line coverage for every production PHP file.
 *
 * Usage: php tools/check-coverage.php build/coverage/clover.xml
 * Missing, empty or incomplete reports fail the build.
 */

declare(strict_types=1);

$reportPath = $argv[1] ?? dirname(__DIR__) . '/build/coverage/clover.xml';

if (!is_file($reportPath)) {
    fwrite(STDERR, "Coverage report not found: {$reportPath}\n");
    exit(1);
}

libxml_use_internal_errors(true);
$report = simplexml_load_file($reportPath, 'SimpleXMLElement', LIBXML_NONET);

if ($report === false || $report->getName() !== 'coverage') {
    fwrite(STDERR, "Invalid Clover coverage report.\n");
    exit(1);
}

$expected = [];
$source = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(
    dirname(__DIR__) . '/src',
    FilesystemIterator::SKIP_DOTS
));

foreach ($source as $file) {
    if ($file->isFile() && $file->getExtension() === 'php') {
        $expected[$file->getRealPath()] = false;
    }
}

$failures = [];
$total = 0;
$covered = 0;

foreach ($report->xpath('//file') as $file) {
    $path = realpath((string) $file['name']);

    if ($path === false || !array_key_exists($path, $expected)) {
        continue;
    }

    if ($expected[$path]) {
        $failures[] = 'Duplicate file: ' . $path;
        continue;
    }

    $expected[$path] = true;
    $metrics = $file->metrics;
    $statements = (string) $metrics['statements'];
    $coveredStatements = (string) $metrics['coveredstatements'];

    if (
        !ctype_digit($statements) || !ctype_digit($coveredStatements) ||
        (int) $coveredStatements > (int) $statements
    ) {
        $failures[] = 'Invalid metrics: ' . $path;
        continue;
    }

    $lines = $file->xpath('./line[@type="stmt"]');
    $visited = 0;

    foreach ($lines as $line) {
        if ((int) $line['count'] > 0) {
            $visited++;
        } else {
            $failures[] = $path . ':' . $line['num'] . ' is uncovered';
        }
    }

    if (count($lines) !== (int) $statements || $visited !== (int) $coveredStatements) {
        $failures[] = 'Metrics disagree with recorded lines: ' . $path;
    }

    $total += (int) $statements;
    $covered += (int) $coveredStatements;
}

foreach ($expected as $path => $seen) {
    if (!$seen) {
        $failures[] = 'Source file missing from coverage: ' . $path;
    }
}

if ($total === 0) {
    $failures[] = 'Coverage report contains no executable production lines.';
}

if ($covered !== $total) {
    $failures[] = sprintf(
        'Required: 100%%; measured: %.2f%% (%d/%d).',
        $total > 0 ? 100 * $covered / $total : 0,
        $covered,
        $total
    );
}

if ($failures !== []) {
    fwrite(STDERR, implode("\n", $failures) . "\n");
    exit(1);
}

printf(
    "Coverage gate passed: 100%% (%d/%d executable lines, %d source files).\n",
    $covered,
    $total,
    count($expected)
);
