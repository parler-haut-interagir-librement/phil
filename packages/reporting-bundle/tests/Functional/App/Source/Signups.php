<?php

declare(strict_types=1);

namespace Phil\ReportingBundle\Tests\Functional\App\Source;

use Override;
use Phil\ReportingBundle\Application\Contract\SeriesSource;
use Phil\ReportingBundle\Domain\ValueObject\Period;
use Phil\ReportingBundle\Domain\ValueObject\Series;
use Phil\ReportingBundle\Domain\ValueObject\SeriesSet;

/**
 * Series of the application: the sign-ups of each week of the period.
 */
final class Signups implements SeriesSource
{
    #[Override]
    public static function key(): string
    {
        return 'signups';
    }

    #[Override]
    public function title(): string
    {
        return 'Sign-ups <weekly>';
    }

    #[Override]
    public function series(Period $period): SeriesSet
    {
        $weeks = $period->split('1 week');

        return new SeriesSet(
            array_map(static fn (Period $week): string => $week->start->format('M j'), $weeks),
            [new Series('Sign-ups', array_map(static fn (Period $week): float => (float) $week->start->format('j'), $weeks))],
        );
    }
}
