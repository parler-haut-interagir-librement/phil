<?php

declare(strict_types=1);

namespace Phil\ReportingBundle\Tests\Unit\Fixture;

use Override;
use Phil\ReportingBundle\Application\Contract\PayrollSource;
use Phil\ReportingBundle\Domain\ValueObject\Period;

/**
 * The payroll of each period, by the first day of the period; null for another.
 */
final readonly class Payrolls implements PayrollSource
{
    /**
     * @param array<string, int> $byStart
     */
    public function __construct(
        private array $byStart,
    ) {
    }

    #[Override]
    public function payroll(Period $period): ?int
    {
        return $this->byStart[$period->start->format('Y-m-d')] ?? null;
    }
}
