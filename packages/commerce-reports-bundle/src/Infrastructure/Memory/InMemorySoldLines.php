<?php

declare(strict_types=1);

namespace Phil\CommerceReportsBundle\Infrastructure\Memory;

use Override;
use Phil\CommerceReportsBundle\Application\Contract\SoldLines;
use Phil\CommerceReportsBundle\Domain\ValueObject\SoldLine;
use Phil\ReportingBundle\Domain\ValueObject\Period;

/**
 * Sold lines given once, for tests and for an application that reads its sales elsewhere: the lines sold inside the
 * period, in their order. Without lines, no sale.
 */
final readonly class InMemorySoldLines implements SoldLines
{
    /**
     * @param list<SoldLine> $lines
     */
    public function __construct(
        private array $lines = [],
    ) {
    }

    #[Override]
    public function between(Period $period): iterable
    {
        foreach ($this->lines as $line) {
            if ($period->contains($line->soldAt)) {
                yield $line;
            }
        }
    }
}
