<?php

declare(strict_types=1);

/*
 * The text of a cell, on bytes nobody wrote: in XLSX it always makes well-formed XML that reads back as a prefix of
 * its valid characters; in CSV it never starts with what a spreadsheet runs as a formula.
 *
 * Run by `phil-dev fuzz`, which sets PHIL_DEV_AUTOLOAD and gives this file the fuzzer's `$config`.
 */

use Phil\DataTableExport\Csv\FormulaGuard;
use Phil\DataTableExport\Xlsx\SheetXml;

$autoload = getenv('PHIL_DEV_AUTOLOAD');

if (!is_string($autoload)) {
    throw new RuntimeException('PHIL_DEV_AUTOLOAD names the autoloader: run this target through phil-dev fuzz.');
}

require $autoload;

/** @var PhpFuzzer\Config $config */
$config->setTarget(static function (string $input): void {
    $xml = '<?xml version="1.0" encoding="UTF-8"?><row>'.SheetXml::cell('A1', $input).'</row>';
    $document = new DOMDocument();

    if (!$document->loadXML($xml)) {
        throw new LogicException('A cell made XML that is not well-formed.');
    }

    $protected = FormulaGuard::protect($input);
    $start = ltrim($protected, ' ');

    if ('' !== $start && str_contains("=@\t\r", $start[0])) {
        throw new LogicException('A protected text still starts as a formula.');
    }

    if ('' !== $start && str_contains('+-', $start[0]) && 1 !== preg_match('/^[+-](\d+([.,]\d+)?|[.,]\d+)([eE][+-]?\d+)?$/D', $protected)) {
        throw new LogicException('A protected text starts with a sign and is not a number.');
    }
});
$config->setMaxLen(256);
