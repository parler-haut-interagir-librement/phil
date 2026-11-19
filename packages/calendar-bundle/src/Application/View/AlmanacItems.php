<?php

declare(strict_types=1);

namespace Phil\CalendarBundle\Application\View;

use Closure;
use DateTimeImmutable;
use Override;
use Phil\Almanac\Almanac;
use Phil\Almanac\ComputedDate;
use Phil\Almanac\Exception\OutOfRange;

/**
 * The almanac's dates as items, from `phil/almanac`: a holiday, a full moon at its time. They link to nothing.
 *
 * Given as a closure, the almanac is asked for at each period, so that a long-lived service shows the dates stored
 * since it was built. A period with a year the almanac cannot compute — Easter before 1583 — holds no almanac item
 * rather than no page.
 */
final readonly class AlmanacItems implements ItemSource
{
    /** The almanac's service, which `phil/almanac-bundle` registers. */
    public const string ALMANAC = Almanac::class;

    /**
     * @param Almanac|Closure(): Almanac $almanac
     */
    public function __construct(
        private Almanac|Closure $almanac,
    ) {
    }

    #[Override]
    public function items(DateTimeImmutable $from, DateTimeImmutable $until): array
    {
        $zone = $from->getTimezone();
        $almanac = $this->almanac instanceof Almanac ? $this->almanac : ($this->almanac)();

        try {
            $dates = $almanac->between($from, $until);
        } catch (OutOfRange) {
            return [];
        }

        return array_map(static fn (ComputedDate $date): Item => new Item(
            'almanac:'.$date->definition->key.'@'.$date->span->start->format('Ymd\THis'),
            $date->definition->title,
            $date->span->allDay ? new DateTimeImmutable($date->span->start->format('Y-m-d'), $zone) : $date->span->start->setTimezone($zone),
            $date->span->allDay ? new DateTimeImmutable($date->span->end->format('Y-m-d'), $zone) : $date->span->end->setTimezone($zone),
            $date->span->allDay,
            Item::ALMANAC,
            tags: $date->definition->tags,
        ), $dates);
    }
}
