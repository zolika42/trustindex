#!/usr/bin/env php
<?php

declare(strict_types=1);

if ($argc < 2) {
    fwrite(\STDERR, "Usage: php bin/check-coverage.php <clover.xml> [minimum-percent]\n");
    exit(2);
}

$path = $argv[1];
$minimum = isset($argv[2]) ? (float) $argv[2] : 90.0;

if (!is_file($path)) {
    fwrite(\STDERR, sprintf("Coverage file not found: %s\n", $path));
    exit(2);
}

$xml = simplexml_load_file($path);
if (false === $xml) {
    fwrite(\STDERR, "Could not parse Clover coverage XML.\n");
    exit(2);
}

$metrics = $xml->project->metrics;
$statements = (int) $metrics['statements'];
$coveredStatements = (int) $metrics['coveredstatements'];

$coverage = 0 === $statements ? 100.0 : ($coveredStatements / $statements) * 100;

printf("Meaningful-code line coverage: %.2f%% (minimum: %.2f%%)\n", $coverage, $minimum);

if ($coverage + 0.0001 < $minimum) {
    fwrite(\STDERR, "Coverage threshold not met.\n");
    exit(1);
}
