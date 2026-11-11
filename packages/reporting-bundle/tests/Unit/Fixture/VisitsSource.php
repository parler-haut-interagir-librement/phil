<?php

declare(strict_types=1);

namespace Phil\ReportingBundle\Tests\Unit\Fixture;

use Override;
use Phil\ReportingBundle\Application\Contract\SeriesSource;
use Phil\ReportingBundle\Domain\ValueObject\Period;
use Phil\ReportingBundle\Domain\ValueObject\Series;
use Phil\ReportingBundle\Domain\ValueObject\SeriesSet;

/**
 * A series source with two series over three labels, one value missing and one below zero.
 */
final class VisitsSource implements SeriesSource
{
    #[Override]
    public static function key(): string
    {
        return 'visits';
    }

    #[Override]
    public function title(): string
    {
        return 'Visits';
    }

    #[Override]
    public function series(Period $period): SeriesSet
    {
        return new SeriesSet(['Mon', 'Tue', 'Wed'], [
            new Series('Pages', [40.0, 20.0, null]),
            new Series('Balance', [-10.0, 5.5, 0.0]),
        ]);
    }
}
