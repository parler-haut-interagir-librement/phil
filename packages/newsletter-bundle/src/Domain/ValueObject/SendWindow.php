<?php

declare(strict_types=1);

namespace Phil\NewsletterBundle\Domain\ValueObject;

use DateTimeImmutable;
use DateTimeZone;
use Exception;
use Phil\NewsletterBundle\Domain\Exception\InvalidNewsletterValue;

/**
 * The hours a sender profile sends in: days of the week, a start and an end in a time zone. A message whose turn comes
 * outside the window waits for its next opening.
 *
 * The end comes after the start on the same day; a window across midnight is two windows' worth of days and is
 * written as the hours of each day instead.
 */
final readonly class SendWindow
{
    /**
     * @param list<int> $days  ISO days of the week, 1 for Monday to 7 for Sunday
     * @param int       $from  minutes after midnight the window opens at
     * @param int       $until minutes after midnight the window closes at
     */
    private function __construct(
        public array $days,
        public int $from,
        public int $until,
        public DateTimeZone $timezone,
    ) {
    }

    /**
     * @param list<int> $days ISO days of the week
     *
     * @throws InvalidNewsletterValue when a day, an hour or the time zone is refused, or the window is empty
     */
    public static function of(array $days, string $from, string $until, string $timezone = 'UTC'): self
    {
        $days = array_values(array_unique($days));
        sort($days);

        if ([] === $days || [] !== array_diff($days, range(1, 7))) {
            throw InvalidNewsletterValue::because('The days of a send window are ISO days, 1 for Monday to 7 for Sunday, at least one.');
        }

        $start = self::minutes($from);
        $end = self::minutes($until);

        if ($end <= $start) {
            throw InvalidNewsletterValue::because(\sprintf('A send window closes after it opens: %s is not after %s.', $until, $from));
        }

        try {
            $zone = new DateTimeZone($timezone);
        } catch (Exception) {
            throw InvalidNewsletterValue::because(\sprintf('"%s" is not a time zone.', $timezone));
        }

        return new self($days, $start, $end, $zone);
    }

    /**
     * Every hour of every day.
     */
    public static function always(): self
    {
        return new self([1, 2, 3, 4, 5, 6, 7], 0, 1440, new DateTimeZone('UTC'));
    }

    public function contains(DateTimeImmutable $at): bool
    {
        $local = $at->setTimezone($this->timezone);
        $minutes = (int) $local->format('G') * 60 + (int) $local->format('i');

        return \in_array((int) $local->format('N'), $this->days, true) && $minutes >= $this->from && $minutes < $this->until;
    }

    /**
     * The instant itself when it is in the window, else the next opening.
     */
    public function next(DateTimeImmutable $at): DateTimeImmutable
    {
        if ($this->contains($at)) {
            return $at;
        }

        $local = $at->setTimezone($this->timezone);

        for ($day = 0; $day <= 7; ++$day) {
            $opening = $local->modify(\sprintf('+%d days', $day))->setTime(intdiv($this->from, 60), $this->from % 60);

            if (\in_array((int) $opening->format('N'), $this->days, true) && $opening->getTimestamp() > $at->getTimestamp()) {
                return $opening->setTimezone($at->getTimezone());
            }
        }

        return $at;
    }

    private static function minutes(string $time): int
    {
        if ('24:00' === $time) {
            return 1440;
        }

        if (1 !== preg_match('/^([01][0-9]|2[0-3]):([0-5][0-9])$/D', $time, $parts)) {
            throw InvalidNewsletterValue::because(\sprintf('"%s" is not an hour of a send window: HH:MM, from 00:00 to 24:00.', $time));
        }

        return (int) $parts[1] * 60 + (int) $parts[2];
    }
}
