<?php

declare(strict_types=1);

namespace Phil\ReportingBundle\Domain\Format;

/**
 * How the value of a metric reads: a number, a share of one written as a percentage, or an amount in the currency the
 * metric names.
 */
enum MetricFormat: string
{
    case Number = 'number';
    case Percent = 'percent';
    case Currency = 'currency';
}
