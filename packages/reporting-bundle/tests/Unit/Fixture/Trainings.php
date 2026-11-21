<?php

declare(strict_types=1);

namespace Phil\ReportingBundle\Tests\Unit\Fixture;

use DateTimeImmutable;
use DateTimeZone;
use Override;
use Phil\ReportingBundle\Application\Contract\TrainingSource;
use Phil\ReportingBundle\Domain\Training\TrainingActivity;
use Phil\ReportingBundle\Domain\ValueObject\Period;

/**
 * The training activities of a learning system, in Toronto, given in the order of the test; the periods it was asked
 * for are kept.
 */
final class Trainings implements TrainingSource
{
    /** @var list<Period> */
    public array $asked = [];

    /**
     * @param list<TrainingActivity> $activities
     */
    public function __construct(
        private readonly array $activities = [],
        private readonly string $currency = 'CAD',
    ) {
    }

    public static function on(string $day): DateTimeImmutable
    {
        return new DateTimeImmutable($day, new DateTimeZone('America/Toronto'));
    }

    public static function year(string $year = '2026'): Period
    {
        return new Period(self::on($year.'-01-01'), self::on(((int) $year + 1).'-01-01'));
    }

    #[Override]
    public function currency(): string
    {
        return $this->currency;
    }

    #[Override]
    public function activities(Period $period): iterable
    {
        $this->asked[] = $period;

        yield from $this->activities;
    }
}
