<?php

declare(strict_types=1);

namespace Phil\Spreadsheet;

/**
 * What the first row of a written sheet holds.
 */
enum Headings: string
{
    /** The label of each column, or its code when it has none. */
    case Labels = 'labels';
    /** The code of each column. */
    case Codes = 'codes';
    /** No heading row: the sheet starts with the data. */
    case None = 'none';
}
