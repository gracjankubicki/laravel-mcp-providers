<?php

declare(strict_types=1);

$path = $argv[1] ?? 'coverage.xml';
$required = isset($argv[2]) ? (float) $argv[2] : 100.0;

if (! is_file($path)) {
    fwrite(STDERR, "Coverage file not found: {$path}\n");
    exit(1);
}

$xml = simplexml_load_file($path);
if ($xml === false) {
    fwrite(STDERR, "Invalid coverage XML: {$path}\n");
    exit(1);
}

$total = 0;
$covered = 0;

foreach ($xml->project->package as $package) {
    foreach ($package->file as $file) {
        $name = (string) $file['name'];
        if (! str_contains($name, '/src/')) {
            continue;
        }

        foreach ($file->line as $line) {
            if ((string) $line['type'] !== 'stmt') {
                continue;
            }

            $total++;
            if ((int) $line['count'] > 0) {
                $covered++;
            }
        }
    }
}

if ($total === 0) {
    fwrite(STDERR, "No source statements found under /src/ in {$path}\n");
    exit(1);
}

$percentage = ($covered / $total) * 100;
printf("Coverage (src lines): %.2f%% (%d/%d)\n", $percentage, $covered, $total);

if ($percentage + 1e-9 < $required) {
    fwrite(STDERR, "Required coverage is {$required}%, got ".number_format($percentage, 2)."%\n");
    exit(1);
}
