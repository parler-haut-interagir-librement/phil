<?php

declare(strict_types=1);

namespace Phil\Spreadsheet;

use Phil\Spreadsheet\Exception\InvalidSpreadsheet;

/**
 * A sheet to write: its name, its columns and its rows. The rows are read once, in order, while the file is written,
 * so a generator keeps the memory of one row whatever their number.
 *
 * Each row is an array of cells or of PHP values, which `Cell::from()` types by their PHP type; its keys are ignored.
 */
final readonly class Sheet
{
    /** The most characters of a sheet name, as spreadsheets allow. */
    public const int NAME_LENGTH = 31;

    /**
     * @param string                 $name    the sheet's name: 1 to 31 characters, none of `[ ] : * ? / \`, no apostrophe
     *                                        at either end
     * @param iterable<array<mixed>> $rows    the rows of data
     * @param list<Column>           $columns the columns, which give the heading row and the widths; none when empty
     *
     * @throws InvalidSpreadsheet when the name is not one a spreadsheet allows
     */
    public function __construct(
        public string $name,
        public iterable $rows,
        public array $columns = [],
    ) {
        if (!self::isValidName($name)) {
            throw InvalidSpreadsheet::because(\sprintf('"%s" is not a sheet name: 1 to 31 characters, none of [ ] : * ? / \\, no apostrophe at either end.', mb_scrub(mb_substr($name, 0, 40), 'UTF-8')));
        }
    }

    /**
     * A sheet name made from any title: the characters a name refuses replaced by spaces, apostrophes at the ends
     * removed, cut to 31 characters, `Sheet1` when nothing is left.
     */
    public static function nameFrom(string $title): string
    {
        $name = (string) preg_replace('/[\[\]:*?\/\\\\\x00-\x1F\x7F]/u', ' ', mb_scrub($title, 'UTF-8'));
        $name = trim(mb_substr(trim($name, " '"), 0, self::NAME_LENGTH), " '");

        return self::isValidName($name) ? $name : 'Sheet1';
    }

    private static function isValidName(string $name): bool
    {
        return '' !== $name
            && mb_check_encoding($name, 'UTF-8')
            && mb_strlen($name, 'UTF-8') <= self::NAME_LENGTH
            && !str_starts_with($name, "'")
            && !str_ends_with($name, "'")
            && 'history' !== mb_strtolower($name, 'UTF-8')
            && 1 !== preg_match('/[\[\]:*?\/\\\\\x00-\x1F\x7F]/', $name);
    }
}
