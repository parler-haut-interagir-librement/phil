<?php

declare(strict_types=1);

namespace Phil\AnalyticsBundle\Domain\Measure;

/**
 * How far down a page was read, in closed bands: the only form of a scroll position a counter holds.
 */
enum ScrollDepth: int
{
    case None = 0;
    case Quarter = 25;
    case Half = 50;
    case ThreeQuarters = 75;
    case Whole = 100;
}
