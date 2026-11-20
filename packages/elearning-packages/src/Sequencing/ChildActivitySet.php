<?php

declare(strict_types=1);

namespace Phil\ElearningPackages\Sequencing;

/**
 * How many of the children that contribute to a rollup must meet its conditions.
 */
enum ChildActivitySet: string
{
    case All = 'all';
    case Any = 'any';
    case None = 'none';
    case AtLeastCount = 'atLeastCount';
    case AtLeastPercent = 'atLeastPercent';
}
