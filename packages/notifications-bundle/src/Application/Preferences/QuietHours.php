<?php

declare(strict_types=1);

namespace Phil\NotificationsBundle\Application\Preferences;

use DateTimeImmutable;
use DateTimeZone;
use Exception;
use Phil\NotificationsBundle\Domain\Exception\InvalidNotification;

/**
 * The hours a recipient is not interrupted, in their time zone, from `start` included to `end` excluded; a range past
 * midnight, `22:00` to `07:00`, goes over to the next day, and the same start and end make no quiet hours.
 */
final readonly class QuietHours
{
    private const string TIME = '/^([01]\d|2[0-3]):([0-5]\d)$/D';

    private function __construct(
        public string $start,
        public string $end,
        public DateTimeZone $timezone,
    ) {
    }

    /**
     * @param string $start    `HH:MM`, on a 24-hour clock
     * @param string $end      `HH:MM`, on a 24-hour clock
     * @param string $timezone an identifier of the time zone database
     *
     * @throws InvalidNotification when a time or the time zone cannot be read
     */
    public static function of(string $start, string $end, string $timezone = 'UTC'): self
    {
        foreach ([$start, $end] as $time) {
            if (1 !== preg_match(self::TIME, $time)) {
                throw InvalidNotification::because(\sprintf('The quiet hours are times written "HH:MM", not "%s".', $time));
            }
        }

        try {
            $zone = new DateTimeZone($timezone);
        } catch (Exception) {
            throw InvalidNotification::because(\sprintf('The time zone of the quiet hours, "%s", is not one of the time zone database.', $timezone));
        }

        return new self($start, $end, $zone);
    }

    public function contains(DateTimeImmutable $at): bool
    {
        $now = $at->setTimezone($this->timezone)->format('H:i');

        return $this->start <= $this->end
            ? $now >= $this->start && $now < $this->end
            : $now >= $this->start || $now < $this->end;
    }
}
