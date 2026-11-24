<?php

declare(strict_types=1);

namespace Phil\CommerceReportsBundle\Infrastructure\Memory;

use Override;
use Phil\CommerceReportsBundle\Application\Contract\ReturnedLines;
use Phil\CommerceReportsBundle\Domain\ValueObject\ReturnedLine;
use Phil\ReportingBundle\Domain\ValueObject\Period;

/**
 * Returned lines given once, for tests and until an application connects its returns: the lines returned inside the
 * period, in their order. Without lines, no return.
 */
final readonly class InMemoryReturnedLines implements ReturnedLines
{
    /**
     * @param list<ReturnedLine> $lines
     */
    public function __construct(
        private array $lines = [],
    ) {
    }

    #[Override]
    public function between(Period $period): iterable
    {
        foreach ($this->lines as $line) {
            if ($period->contains($line->returnedAt)) {
                yield $line;
            }
        }
    }
}
