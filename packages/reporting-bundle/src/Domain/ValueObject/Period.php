<?php

declare(strict_types=1);

namespace Phil\ReportingBundle\Domain\ValueObject;

use DateInterval;
use DateMalformedIntervalStringException;
use DatePeriod;
use DateTimeImmutable;
use Phil\ReportingBundle\Domain\Exception\InvalidFigures;

/**
 * A span of time, from its start included to its end excluded, the window a metric or a series is measured over.
 */
final readonly class Period
{
    /** The most buckets `split()` gives, so that a step too small for a long period costs no more than a long chart. */
    public const int MAX_BUCKETS = 500;

    /**
     * @throws InvalidFigures when the end is not after the start
     */
    public function __construct(
        public DateTimeImmutable $start,
        public DateTimeImmutable $end,
    ) {
        if ($end <= $start) {
            throw InvalidFigures::because('a period ends after it starts');
        }
    }

    /**
     * Whether the moment falls in the period, its start included and its end excluded.
     */
    public function contains(DateTimeImmutable $moment): bool
    {
        return $moment >= $this->start && $moment < $this->end;
    }

    /**
     * The period cut in consecutive buckets of the step — `1 day`, `1 week`, `1 month` —, the last one cut at the end.
     *
     * @return list<self>
     *
     * @throws InvalidFigures when the step is not a relative date format, or gives more than 500 buckets
     */
    public function split(string $step): array
    {
        try {
            $interval = DateInterval::createFromDateString($step);
        } catch (DateMalformedIntervalStringException) {
            $interval = null;
        }

        if (!$interval instanceof DateInterval || $this->start->add($interval) <= $this->start) {
            throw InvalidFigures::because(\sprintf('"%s" is not a step forward in time', $step));
        }

        $buckets = [];

        foreach (new DatePeriod($this->start, $interval, $this->end, DatePeriod::EXCLUDE_START_DATE) as $boundary) {
            if (\count($buckets) === self::MAX_BUCKETS - 1) {
                throw InvalidFigures::because(\sprintf('more than %d buckets', self::MAX_BUCKETS));
            }

            $buckets[] = $boundary;
        }

        $periods = [];
        $start = $this->start;

        foreach ([...$buckets, $this->end] as $end) {
            $periods[] = new self($start, $end);
            $start = $end;
        }

        return $periods;
    }
}
