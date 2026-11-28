<?php

declare(strict_types=1);

namespace Phil\Spreadsheet;

/**
 * The kind of a cell, always stated and never guessed from its text: a postal code or a product code written as text
 * keeps its leading zeros.
 */
enum CellType: string
{
    case Empty = 'empty';
    case Text = 'text';
    case Number = 'number';
    case Boolean = 'boolean';
    /** A day, without a time. */
    case Date = 'date';
    /** A day and a time of day. */
    case DateTime = 'date-time';
}
