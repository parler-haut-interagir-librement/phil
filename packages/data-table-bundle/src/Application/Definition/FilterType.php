<?php

declare(strict_types=1);

namespace Phil\DataTableBundle\Application\Definition;

/**
 * The control of a filter and what it asks of the data source.
 */
enum FilterType: string
{
    case Text = 'text';
    case Enum = 'enum';
    case DateRange = 'date_range';
    case Boolean = 'boolean';
}
