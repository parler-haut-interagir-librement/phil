<?php

declare(strict_types=1);

/*
 * The ODS content parser on XML nobody wrote, within small limits: each read of the first table returns rows or throws
 * a ReadRefused; nothing else escapes, no document type is ever expanded, the repetitions never pass the limits, and
 * no row ends with an empty cell.
 *
 * Run by `phil-dev fuzz`, which sets PHIL_DEV_AUTOLOAD and gives this file the fuzzer's `$config`.
 */
use Phil\Spreadsheet\Exception\ReadRefused;
use Phil\Spreadsheet\Internal\SafeXmlReader;
use Phil\Spreadsheet\Ods\OdsContentParser;
use Phil\Spreadsheet\ReadLimits;

$autoload = getenv('PHIL_DEV_AUTOLOAD');

if (!is_string($autoload)) {
    throw new RuntimeException('PHIL_DEV_AUTOLOAD names the autoloader: run this target through phil-dev fuzz.');
}

require $autoload;

$limits = new ReadLimits(maxRows: 64, maxColumns: 32, maxCells: 512);
$parser = new OdsContentParser($limits);

/** @var PhpFuzzer\Config $config */
$config->setTarget(static function (string $input) use ($limits, $parser): void {
    $reader = SafeXmlReader::fromString($input, 'content.xml', 8192);

    try {
        foreach ($parser->rows($reader, null, 'content.xml') as $row) {
            if ($row->number < 1 || $row->number > $limits->maxRows || [] === $row->cells || count($row->cells) > $limits->maxColumns || $row->cell(count($row->cells) - 1)->isEmpty()) {
                throw new LogicException('A row is out of its limits, or ends with an empty cell.');
            }
        }
    } catch (ReadRefused) {
        return;
    } finally {
        $reader->close();
    }
});
$config->setMaxLen(8192);
