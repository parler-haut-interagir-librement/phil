<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Infrastructure\Reporting;

use Override;
use Phil\CoursesBundle\Application\Contract\TrainingCosts;
use Phil\CoursesBundle\Application\Contract\TrainingUnits;
use Phil\CoursesBundle\Application\Training\TrainingCost;
use Phil\CoursesBundle\Domain\Contract\TranscriptRepository;
use Phil\CoursesBundle\Domain\Model\TranscriptEntry;
use Phil\CoursesBundle\Domain\Training\CreditUnit;
use Phil\CoursesBundle\Domain\ValueObject\Credits;
use Phil\ReportingBundle\Application\Contract\TrainingSource;
use Phil\ReportingBundle\Domain\Training\TrainingActivity;
use Phil\ReportingBundle\Domain\ValueObject\Period;

/**
 * The training source of phil/reporting-bundle, from the transcripts: each completion of the period is an activity on
 * the moment it was completed, its hours those of the credits it granted — hours as they are, continuing education
 * units at so many hours each —, its cost what the application's `TrainingCosts` answers, and its unit the learner's,
 * from `TrainingUnits`.
 *
 * The units are asked once per learner and per report, at most `MAX_LEARNERS` held at a time: nothing is kept between
 * two reports.
 */
final readonly class CoursesTrainingSource implements TrainingSource
{
    /** The units of the learners a report holds before it forgets them and asks again. */
    public const int MAX_LEARNERS = 10_000;

    /**
     * @param string       $currency the ISO 4217 code of the costs: `CAD`
     * @param int<1, 1000> $ceuHours the hours of a continuing education unit
     */
    public function __construct(
        private TranscriptRepository $transcripts,
        private string $currency = 'CAD',
        private int $ceuHours = Credits::CEU_HOURS,
        private ?TrainingCosts $costs = null,
        private ?TrainingUnits $units = null,
    ) {
    }

    #[Override]
    public function currency(): string
    {
        return $this->currency;
    }

    #[Override]
    public function activities(Period $period): iterable
    {
        $units = [];

        foreach ($this->transcripts->completedBetween($period->start, $period->end) as $entry) {
            $cost = $this->costs?->cost($entry) ?? TrainingCost::none();
            $learner = $entry->learner->value;

            if (!\array_key_exists($learner, $units)) {
                if (\count($units) >= self::MAX_LEARNERS) {
                    $units = [];
                }

                $units[$learner] = $this->units?->unitOf($entry->learner);
            }

            yield new TrainingActivity(
                on: $entry->completedAt,
                hundredthsOfHours: $this->hours($entry),
                cost: $cost->cost,
                eligibleCost: $cost->eligibleCost,
                unit: $units[$learner],
            );
        }
    }

    /**
     * The hours of the credits a completion granted, in hundredths; none without credits.
     */
    private function hours(TranscriptEntry $entry): int
    {
        if (!$entry->credits instanceof Credits) {
            return 0;
        }

        $hundredths = CreditUnit::Ceu === $entry->credits->unit ? $entry->credits->hundredths * $this->ceuHours : $entry->credits->hundredths;

        return min($hundredths, TrainingActivity::MAX_HUNDREDTHS);
    }
}
