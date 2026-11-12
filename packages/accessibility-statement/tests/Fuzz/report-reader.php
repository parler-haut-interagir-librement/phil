<?php

declare(strict_types=1);

/*
 * The report reader on inputs nobody wrote: whatever it reads, it returns a report that serializes to JSON, or refuses
 * the input with an InvalidReport, and never throws anything else nor warns.
 *
 * Run by `phil-dev fuzz`, which sets PHIL_DEV_AUTOLOAD and gives this file the fuzzer's `$config`.
 */

use Phil\AccessibilityStatement\Audit\ReportReader;
use Phil\AccessibilityStatement\Exception\InvalidReport;

$autoload = getenv('PHIL_DEV_AUTOLOAD');

if (!is_string($autoload)) {
    throw new RuntimeException('PHIL_DEV_AUTOLOAD names the autoloader: run this target through phil-dev fuzz.');
}

require $autoload;

$reader = new ReportReader();

/** @var PhpFuzzer\Config $config */
$config->setTarget(static function (string $input) use ($reader): void {
    try {
        json_encode($reader->fromJson($input), \JSON_THROW_ON_ERROR | \JSON_INVALID_UTF8_SUBSTITUTE);
    } catch (InvalidReport) {
        // A report refused whole is an answer.
    }
});
$config->setMaxLen(4096);
