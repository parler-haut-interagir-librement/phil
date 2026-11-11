<?php

declare(strict_types=1);

namespace Phil\ReportingBundle\Application\Dashboard;

/**
 * How a chart widget draws its series: an SVG image with a table of every value beside it, horizontal bars of HTML
 * and CSS that are a table themselves, or the table alone.
 */
enum ChartRendering: string
{
    case Svg = 'svg';
    case Html = 'html';
    case Table = 'table';
}
