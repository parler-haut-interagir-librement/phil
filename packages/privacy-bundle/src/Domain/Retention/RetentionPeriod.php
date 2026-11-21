<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Domain\Retention;

use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use Exception;
use Phil\PrivacyBundle\Domain\Exception\InvalidPrivacyValue;

/**
 * How long a store keeps a record.
 *
 * Four shapes, because a schedule holds all four and only one of them is a number:
 *
 * - **fixed**: an ISO 8601 duration (`P90D`, `P24M`, `P7Y`), the only shape a purge job can compute a cutoff from;
 * - **until an event**: "the life of the account", "until the batch is applied" — enforced by whatever ends it;
 * - **indefinite**: kept on purpose, with no end;
 * - **not yet fixed**: a counted blank, naming where the value will be decided. It is not a duration, and it
 *   never produces a cutoff.
 *
 * **Cutoffs are computed in UTC, and month arithmetic clamps rather than overflows.** PHP turns 31 March minus one
 * month into 3 March; a purge on that cutoff deletes records three days early. Here it is 28 February (29 in a
 * leap year): a record is never purged before its period has fully run.
 */
final readonly class RetentionPeriod
{
    /**
     * The instants `lastsAtLeast()` compares from: the ends and starts of month around the leap days of a four-year
     * cycle.
     */
    private const array REFERENCES = [
        '2028-01-01', '2028-01-31', '2028-02-28', '2028-02-29', '2028-03-01', '2028-03-31', '2028-12-31',
        '2029-01-01', '2029-02-28', '2029-03-01', '2029-03-31', '2029-12-31',
        '2030-01-01', '2030-02-28', '2030-03-01', '2030-03-31', '2030-12-31',
        '2031-01-01', '2031-02-28', '2031-03-01', '2031-03-31', '2031-12-31',
        '2032-02-29', '2032-03-01',
    ];

    private const string ISO_8601 = '/^P(?!$)(\d+Y)?(\d+M)?(\d+W)?(\d+D)?(T(?=\d)(\d+H)?(\d+M)?(\d+S)?)?$/D';

    private function __construct(
        public RetentionPeriodKind $kind,
        public ?string $duration,
        public ?string $note,
    ) {
    }

    public static function fixed(string $isoDuration): self
    {
        if (1 !== preg_match(self::ISO_8601, $isoDuration)) {
            throw InvalidPrivacyValue::because(\sprintf('"%s" is not an ISO 8601 duration such as "P90D" or "P24M".', $isoDuration));
        }

        $interval = self::interval($isoDuration);

        if (0 === $interval->y + $interval->m + $interval->d + $interval->h + $interval->i + $interval->s) {
            throw InvalidPrivacyValue::because(\sprintf('"%s" is a zero duration: a record kept for no time is a record that is not stored.', $isoDuration));
        }

        return new self(RetentionPeriodKind::Fixed, $isoDuration, null);
    }

    public static function untilEvent(string $event): self
    {
        return new self(RetentionPeriodKind::UntilEvent, null, self::note($event, 'the event that ends the period'));
    }

    public static function indefinite(): self
    {
        return new self(RetentionPeriodKind::Indefinite, null, null);
    }

    public static function notYetFixed(string $decidedIn): self
    {
        return new self(RetentionPeriodKind::NotYetFixed, null, self::note($decidedIn, 'where the value will be decided'));
    }

    public function isFixed(): bool
    {
        return RetentionPeriodKind::Fixed === $this->kind;
    }

    /**
     * Whether this period never purges a record before `$minimum` would: a period that is not a number never purges,
     * and two durations are compared through their cutoffs from the ends of month and the leap days of a four-year
     * cycle, where `P1826D` and `P5Y` part ways.
     */
    public function lastsAtLeast(self $minimum): bool
    {
        foreach (self::REFERENCES as $reference) {
            $now = new DateTimeImmutable($reference, new DateTimeZone('UTC'));
            $mine = $this->cutoff($now);
            $floor = $minimum->cutoff($now);

            if ($mine instanceof DateTimeImmutable && $floor instanceof DateTimeImmutable && $mine->getTimestamp() > $floor->getTimestamp()) {
                return false;
            }
        }

        return true;
    }

    /**
     * The instant before which a record has outlived this period, or `null` when the period is not a number.
     */
    public function cutoff(DateTimeImmutable $now): ?DateTimeImmutable
    {
        if (null === $this->duration) {
            return null;
        }

        $utc = $now->setTimezone(new DateTimeZone('UTC'));
        $interval = self::interval($this->duration);
        $months = $interval->y * 12 + $interval->m;

        // Months first, by hand and clamped to the end of the month; then the rest of the interval. With no
        // month in the duration, the first step leaves the date as it is.
        [$year, $month, $day] = array_map(intval(...), explode('-', $utc->format('Y-n-j')));
        $index = $year * 12 + $month - 1 - $months;
        $year = intdiv($index, 12);
        $month = $index % 12 + 1;
        // Day zero of the next month is the last day of this one.
        $lastDay = (int) $utc->setDate($year, $month + 1, 0)->format('j');
        $utc = $utc->setDate($year, $month, min($day, $lastDay));

        $interval->y = 0;
        $interval->m = 0;

        return $utc->sub($interval);
    }

    private static function interval(string $isoDuration): DateInterval
    {
        try {
            return new DateInterval($isoDuration);
        } catch (Exception) {
            throw InvalidPrivacyValue::because(\sprintf('"%s" is not an ISO 8601 duration such as "P90D" or "P24M".', $isoDuration));
        }
    }

    private static function note(string $text, string $what): string
    {
        $text = trim($text);

        if ('' === $text) {
            throw InvalidPrivacyValue::because(\sprintf('A retention period of this kind must name %s.', $what));
        }

        return $text;
    }
}
