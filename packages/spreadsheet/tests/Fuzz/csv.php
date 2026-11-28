<?php

declare(strict_types=1);

/*
 * The CSV reader on bytes nobody wrote, within small limits: each read returns rows or throws a ReadRefused; nothing
 * else escapes, every row lies within the limits, and no row ends with an empty cell.
 *
 * Run by `phil-dev fuzz`, which sets PHIL_DEV_AUTOLOAD and gives this file the fuzzer's `$config`.
 */
use Phil\Spreadsheet\Csv\CsvReader;
use Phil\Spreadsheet\Exception\ReadRefused;
use Phil\Spreadsheet\ReadLimits;

$autoload = getenv('PHIL_DEV_AUTOLOAD');

if (!is_string($autoload)) {
    throw new RuntimeException('PHIL_DEV_AUTOLOAD names the autoloader: run this target through phil-dev fuzz.');
}

require $autoload;

$limits = new ReadLimits(maxFileBytes: 8192, maxRows: 64, maxColumns: 32, maxCells: 512);
$reader = new CsvReader(limits: $limits);
$path = sys_get_temp_dir().'/phil-spreadsheet-fuzz-'.getmypid().'.csv';

/** @var PhpFuzzer\Config $config */
$config->setTarget(static function (string $input) use ($reader, $limits, $path): void {
    file_put_contents($path, $input);

    try {
        foreach ($reader->rows($path) as $row) {
            if ($row->number < 1 || $row->number > $limits->maxRows || [] === $row->cells || count($row->cells) > $limits->maxColumns || $row->cell(count($row->cells) - 1)->isEmpty()) {
                throw new LogicException('A row is out of its limits, or ends with an empty cell.');
            }
        }
    } catch (ReadRefused) {
        return;
    }
});
$config->setMaxLen(8192);
