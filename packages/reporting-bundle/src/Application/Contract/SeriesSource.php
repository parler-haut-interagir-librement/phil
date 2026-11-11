<?php

declare(strict_types=1);

namespace Phil\ReportingBundle\Application\Contract;

use Phil\ReportingBundle\Domain\ValueObject\Period;
use Phil\ReportingBundle\Domain\ValueObject\SeriesSet;

/**
 * Series any bundle or the application measures — visits by day, sales by category —, shown by a chart widget as an
 * image, as bars or as a table. Every service implementing it is a source, found by its key.
 */
interface SeriesSource
{
    /**
     * The source's key, unique among the series sources: letters, digits, dots, dashes and underscores.
     */
    public static function key(): string;

    /**
     * The name the widget shows when its configuration gives none.
     */
    public function title(): string;

    /**
     * The series over the period; `Period::split()` gives its days, weeks or months.
     */
    public function series(Period $period): SeriesSet;
}
