<?php

declare(strict_types=1);

namespace Phil\DataTableBundle\Application\Definition;

/**
 * How the value of a column is read and written.
 */
enum ColumnType: string
{
    case Text = 'text';
    case Number = 'number';
    case Date = 'date';
    case Enum = 'enum';
    case Boolean = 'boolean';
    case Link = 'link';
    case Block = 'block';
}
