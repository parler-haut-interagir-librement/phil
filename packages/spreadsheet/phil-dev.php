<?php

declare(strict_types=1);

use Phil\Spreadsheet\Csv\CsvReader;
use Phil\Spreadsheet\Internal\Output;
use Phil\Spreadsheet\Ods\OdsReader;
use Phil\Spreadsheet\Ods\OdsText;
use Phil\Spreadsheet\Ods\OdsWriter;
use Phil\Spreadsheet\Spreadsheets;
use Phil\Spreadsheet\Xlsx\XlsxReader;
use Phil\Spreadsheet\Xlsx\XlsxText;
use Phil\Spreadsheet\Xlsx\XlsxWriter;

/*
 * What the shared development tooling reads of this package: its paths and its exceptions to the shared
 * configurations, nothing else.
 */
return [
    'paths' => ['src', 'tests'],
    'docs' => ['public' => true, 'translations' => 'blocking'],
    'infection' => [
        'mutators' => [
            // What PHP does by itself: a stream, a reader or an archive that goes out of scope is closed, so the explicit
            // close of the readers and writers, alone or in a `finally`, is a promise of order that nothing can observe.
            'UnwrapFinally' => [
                'ignore' => [
                    CsvReader::class.'::rows',
                    Output::class.'::copy',
                    OdsReader::class.'::rows',
                    OdsReader::class.'::sheetNames',
                    OdsWriter::class.'::content',
                    Spreadsheets::class.'::writeFile',
                    XlsxReader::class.'::dateStyles',
                    XlsxReader::class.'::relationships',
                    XlsxReader::class.'::rows',
                    XlsxReader::class.'::sharedStrings',
                    XlsxReader::class.'::sheetNames',
                    XlsxReader::class.'::workbook',
                    XlsxWriter::class.'::worksheet',
                ],
            ],
            'MethodCallRemoval' => [
                'ignoreSourceCodeByRegex' => [
                    // The same close, before a refusal, of a reader, an archive or a ZIP object that is freed right after.
                    '.*\\$(reader|archive|zip)->close\\(\\);.*',
                    // The temporary file of a writer is closed by `close()` already: `discard()` only matters when writing
                    // fails, and the handle is freed with the writer's frame.
                    '.*\\$file->discard\\(\\);.*',
                ],
            ],
            // A value of the type a function returns anyway: `preg_replace()` gives a string unless the pattern or the
            // text is invalid, which they are not, `fgetcsv()` gives a string for every field but those of a blank line,
            // and a numeric key of an array is an integer whether it is written as one or as a string.
            'CastString' => [
                'ignoreSourceCodeByRegex' => [
                    '.*\\$field = \\(string\\) \\$field;.*',
                    '.*\\$escaped = \\(string\\) preg_replace\\(.*',
                    '.*\\$line = \\(string\\) preg_replace_callback\\(.*',
                ],
            ],
            'CastInt' => [
                'ignoreSourceCodeByRegex' => [
                    '.*\\$codes\\[\\(int\\) \\$xml->getAttribute\\(\'numFmtId\'\\)\\] = .*',
                    '.*\\$this->sharedStrings\\[\\(int\\) \\$index\\].*',
                ],
            ],
            // A blank line has no cell either way: the row it makes is empty and is not given.
            'ArrayItemRemoval' => [
                'ignoreSourceCodeByRegex' => ['.*if \\(\\[null\\] === \\$fields\\) \\{.*'],
            ],
            // What a filter reports as consumed is not read back by anything.
            'Assignment' => ['ignoreSourceCodeByRegex' => ['.*\\$consumed \\+= \\$bucket->datalen;.*']],
            'PlusEqual' => ['ignoreSourceCodeByRegex' => ['.*\\$consumed \\+= \\$bucket->datalen;.*']],
            // The keys of the cells of a row are all different, so no column equals the last one met.
            'GreaterThan' => ['ignoreSourceCodeByRegex' => ['.*if \\(!\\$cell->isEmpty\\(\\) && \\$column > \\$last\\) \\{.*']],
            // An integer of up to 18 digits is already refused when it ends with a line break, by the pattern before it.
            'PregMatchRemoveFlags' => ['ignoreSourceCodeByRegex' => ['.*preg_match\\(\'/\\^\\[\\+-\\]\\?\\\\d\\{1,18\\}\\$/D\', \\$text\\).*']],
            // A run of spaces is at least one space: a missing count is read as 0 or as 1 alike.
            'DecrementInteger' => ['ignoreSourceCodeByRegex' => ['.*\'s\' => str_repeat\\(\' \', max\\(1, min\\(.*']],
            // An empty row repeated is the same empty row each time: its first turn is the only one that counts.
            'Minus' => ['ignoreSourceCodeByRegex' => ['.*\\$this->rowNumber \\+= \\$this->rowRepeat - \\$repeat;.*']],
            // The guard only spares the replacement of a text that holds nothing to replace, and a text with no line
            // break, space or tab is also what the general path writes as a single paragraph; the early return of a text
            // without an escape does the same.
            'LogicalOrAllSubExprNegation' => ['ignoreSourceCodeByRegex' => ['.*if \\(false !== strpbrk\\(\\$escaped, "\\\\x00.*']],
            'ReturnRemoval' => [
                'ignore' => [
                    OdsText::class.'::paragraphs',
                    XlsxText::class.'::unescape',
                ],
            ],
            // The value of a `<v>` outside a cell is never read: the next cell starts from none, and so does a cell that
            // is closed once its end is read.
            'Ternary' => ['ignoreSourceCodeByRegex' => ['.*\\$this->value = \\$this->inCell \\? \'\' : null;.*']],
            'FalseValue' => ['ignoreSourceCodeByRegex' => ['.*\\$this->inCell = false;.*']],
            // The columns met are kept by their letters only to spare the parse of the next cell of the column: kept by
            // the reference, or set again each time, they give the same column.
            'UnwrapRtrim' => ['ignoreSourceCodeByRegex' => ['.*\\$letters = rtrim\\(\\$reference, \'0123456789\'\\);.*']],
            'AssignCoalesce' => [
                'ignoreSourceCodeByRegex' => [
                    '.*\\$this->columns\\[\\$letters\\] \\?\\?= .*',
                    '.*\\$letters\\[\\$index\\] \\?\\?= CellReference::letters.*',
                ],
            ],
        ],
    ],
    'phpstan' => [
        // The fuzz targets run inside nikic/php-fuzzer, which gives them `$config`; PHPStan knows neither.
        'excludePaths' => ['tests/Fuzz/*'],
    ],
    'fuzz' => [
        // The readers of the files people upload, on bytes nobody wrote, from the seeds of tests/Fuzz/corpus/spreadsheet-*.
        'targets' => [
            'spreadsheet-csv' => 'tests/Fuzz/csv.php',
            'spreadsheet-xlsx-sheet' => 'tests/Fuzz/xlsx-sheet.php',
            'spreadsheet-ods-content' => 'tests/Fuzz/ods-content.php',
        ],
    ],
];
