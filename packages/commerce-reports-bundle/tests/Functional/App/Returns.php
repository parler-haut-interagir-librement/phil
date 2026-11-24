<?php

declare(strict_types=1);

namespace Phil\CommerceReportsBundle\Tests\Functional\App;

use Override;
use Phil\CommerceReportsBundle\Application\Contract\ReturnedLines;
use Phil\CommerceReportsBundle\Infrastructure\Memory\InMemoryReturnedLines;
use Phil\CommerceReportsBundle\Tests\Fixture\Lines;
use Phil\ReportingBundle\Domain\ValueObject\Period;

/**
 * The returns of the test application: one book.
 */
final readonly class Returns implements ReturnedLines
{
    #[Override]
    public function between(Period $period): iterable
    {
        return new InMemoryReturnedLines([Lines::returned('BOOK-1', 1, 1250)])->between($period);
    }
}
