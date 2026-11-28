<?php

declare(strict_types=1);

/*
 * The XLSX worksheet parser on XML nobody wrote, within small limits, with two shared texts and two date styles: each
 * read returns rows or throws a ReadRefused; nothing else escapes, no document type is ever expanded, every row lies
 * within the limits, and no row ends with an empty cell.
 *
 * Run by `phil-dev fuzz`, which sets PHIL_DEV_AUTOLOAD and gives this file the fuzzer's `$config`.
 */
use Phil\Spreadsheet\CellType;
use Phil\Spreadsheet\Exception\ReadRefused;
use Phil\Spreadsheet\Internal\SafeXmlReader;
use Phil\Spreadsheet\ReadLimits;
use Phil\Spreadsheet\Xlsx\XlsxSheetParser;

$autoload = getenv('PHIL_DEV_AUTOLOAD');

if (!is_string($autoload)) {
    throw new RuntimeException('PHIL_DEV_AUTOLOAD names the autoloader: run this target through phil-dev fuzz.');
}

require $autoload;

$limits = new ReadLimits(maxRows: 64, maxColumns: 32, maxCells: 512);

/** @var PhpFuzzer\Config $config */
$config->setTarget(static function (string $input) use ($limits): void {
    $reader = SafeXmlReader::fromString($input, 'sheet.xml', 8192);
    $parser = new XlsxSheetParser(['a', 'b'], [1 => CellType::Date, 2 => CellType::DateTime], false, $limits, 'sheet.xml');

    try {
        foreach ($parser->rows($reader) as $row) {
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
