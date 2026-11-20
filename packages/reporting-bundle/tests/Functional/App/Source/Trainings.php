<?php

declare(strict_types=1);

namespace Phil\ReportingBundle\Tests\Functional\App\Source;

use DateTimeImmutable;
use Override;
use Phil\ReportingBundle\Application\Contract\TrainingSource;
use Phil\ReportingBundle\Domain\Training\TrainingActivity;
use Phil\ReportingBundle\Domain\ValueObject\Period;

/**
 * The training activities of the application's learning system: a course in October, without a unit.
 */
final class Trainings implements TrainingSource
{
    #[Override]
    public function currency(): string
    {
        return 'CAD';
    }

    #[Override]
    public function activities(Period $period): iterable
    {
        yield new TrainingActivity(new DateTimeImmutable('2026-10-15 09:00 America/Toronto'), 350, 45_000, 40_000);
    }
}
