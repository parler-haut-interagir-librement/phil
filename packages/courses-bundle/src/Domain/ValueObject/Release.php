<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Domain\ValueObject;

use DateTimeImmutable;
use DateTimeZone;
use Phil\CoursesBundle\Domain\Exception\InvalidCourse;

/**
 * When a module or an activity opens to a learner: at once, at a moment, or a number of days after the learner
 * enrolled, counted from midnight of the enrollment day in the site's time zone, so that the content of "day 3" opens
 * at the same hour of the day for everyone.
 */
final readonly class Release
{
    public const int MAX_DAYS = 3650;

    private function __construct(
        public ?DateTimeImmutable $on,
        public ?int $days,
    ) {
    }

    public static function immediately(): self
    {
        return new self(null, null);
    }

    /**
     * Opens at that moment, kept in UTC.
     */
    public static function on(DateTimeImmutable $moment): self
    {
        return new self($moment->setTimezone(new DateTimeZone('UTC')), null);
    }

    /**
     * @throws InvalidCourse when the days are negative or past ten years
     */
    public static function daysAfterEnrollment(int $days): self
    {
        if ($days < 0 || $days > self::MAX_DAYS) {
            throw InvalidCourse::because(\sprintf('A release is 0 to %d days after the enrollment.', self::MAX_DAYS));
        }

        return new self(null, $days);
    }

    public function isImmediate(): bool
    {
        return !$this->on instanceof DateTimeImmutable && null === $this->days;
    }

    /**
     * The moment it opens for an enrollment of that moment, in UTC; null when it is open at once.
     */
    public function opensAt(DateTimeImmutable $enrolledAt, DateTimeZone $siteZone): ?DateTimeImmutable
    {
        if ($this->on instanceof DateTimeImmutable) {
            return $this->on;
        }

        if (null === $this->days) {
            return null;
        }

        return $enrolledAt->setTimezone($siteZone)->setTime(0, 0)->modify(\sprintf('+%d days', $this->days))->setTimezone(new DateTimeZone('UTC'));
    }

    public function isOpen(DateTimeImmutable $enrolledAt, DateTimeImmutable $now, DateTimeZone $siteZone): bool
    {
        $opensAt = $this->opensAt($enrolledAt, $siteZone);

        return !$opensAt instanceof DateTimeImmutable || $now >= $opensAt;
    }
}
