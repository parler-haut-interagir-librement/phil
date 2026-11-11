<?php

declare(strict_types=1);

namespace Phil\ReportingBundle\Tests\Unit\Fixture;

use Override;
use Phil\ReportingBundle\Application\Contract\MetricSource;
use Phil\ReportingBundle\Domain\ValueObject\Metric;
use Phil\ReportingBundle\Domain\ValueObject\Period;

/**
 * A metric source counting one order per day of the period, and remembering the periods asked.
 */
final class OrdersSource implements MetricSource
{
    /** @var list<Period> */
    public array $asked = [];

    #[Override]
    public static function key(): string
    {
        return 'orders';
    }

    #[Override]
    public function title(): string
    {
        return 'Orders';
    }

    #[Override]
    public function metric(Period $period): Metric
    {
        $this->asked[] = $period;

        return new Metric((float) \count($period->split('1 day')));
    }
}
