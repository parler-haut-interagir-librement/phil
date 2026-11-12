<?php

declare(strict_types=1);

namespace Phil\Almanac;

use DateTimeImmutable;
use DateTimeZone;
use Phil\Almanac\Exception\InvalidRule;
use Phil\Almanac\Rule\Days;
use Phil\Almanac\Rule\Rule;

/**
 * A date the almanac computes: its key, its title, the rule of its start, and how long it lasts — so many days, or
 * until the first date of another rule —, with its tags.
 *
 * - **A rule of days gives whole days.** A rule of the sky gives its instant, or the day it falls on in the time
 *   zone when the definition is all day.
 * - **`until` ends at its first date on or after the start**, in the same year or the next: `saturday on or after
 *   09-15` until `sunday on or after 10-03` is the Oktoberfest.
 */
final readonly class Definition
{
    /**
     * @param list<string> $tags
     */
    public function __construct(
        public string $key,
        public string $title,
        public Rule $rule,
        public ?Rule $until = null,
        public int $days = 1,
        public bool $allDay = true,
        public array $tags = [],
    ) {
        if (1 !== preg_match('/^[a-z0-9][a-z0-9_.-]{0,99}$/D', $key)) {
            throw InvalidRule::because(\sprintf('"%s" is not a key: a lowercase letter or digit, then up to 99 of them, ".", "-" or "_".', $key));
        }

        if ('' === trim($title)) {
            throw InvalidRule::because(\sprintf('The date "%s" has no title.', $key));
        }

        if ($days < 1 || $days > 366 || ($until instanceof Rule && 1 !== $days)) {
            throw InvalidRule::because(\sprintf('The date "%s" lasts 1 to 366 days, or until another rule; not both.', $key));
        }

        if (!$allDay && (!$rule->isInstant() || $until instanceof Rule || 1 !== $days)) {
            throw InvalidRule::because(\sprintf('Only an instant of the sky, a phase, a season or an eclipse, can be shown at its time; "%s" is %s.', $key, $rule));
        }
    }

    /**
     * Its spans that start in a year.
     *
     * @return list<DateSpan>
     */
    public function spans(int $year, DateTimeZone $zone): array
    {
        $spans = [];

        foreach ($this->rule->in($year, $zone) as $start) {
            if (!$this->allDay) {
                $spans[] = new DateSpan($start, $start, false);

                continue;
            }

            $day = $start->setTime(0, 0);
            $spans[] = new DateSpan($day, $this->until instanceof Rule ? $this->end($day, $this->until, $zone) : $day->modify(\sprintf('+%d days', $this->days - 1)), true);
        }

        return $spans;
    }

    private function end(DateTimeImmutable $day, Rule $until, DateTimeZone $zone): DateTimeImmutable
    {
        foreach ([$day, $day->modify('+1 year')] as $in) {
            foreach ($until->in(Days::year($in, $zone), $zone) as $end) {
                if ($end >= $day) {
                    return $end->setTime(0, 0);
                }
            }
        }

        return $day;
    }
}
