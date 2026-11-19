<?php

declare(strict_types=1);

namespace Phil\CalendarBundle\Application\View;

use DateTimeInterface;
use DateTimeZone;
use InvalidArgumentException;
use Phil\CalendarBundle\Domain\Model\Venue;
use Phil\CalendarBundle\Domain\Repository\VenueRepository;
use Phil\CalendarBundle\Domain\ValueObject\Member;

/**
 * The calendar's views, laid out for a template: the period of a view from a date, in the calendar's time zone and
 * weeks, and its month, its hours — shared between hosts, venues or tags —, its list.
 */
final readonly class CalendarViews
{
    public const string HOSTS = 'hosts';

    public const string VENUES = 'venues';

    public const string TAGS = 'tags';

    public function __construct(
        private Items $items,
        private VenueRepository $venues,
        private string $timezone = 'UTC',
        private int $firstWeekday = 1,
        private ?HostLabels $hostLabels = null,
    ) {
    }

    public function period(CalendarView $view, DateTimeInterface $date): Period
    {
        return Period::of($view, $date, $this->zone(), $this->firstWeekday);
    }

    /**
     * The calendar's time zone: a date written `Y-m-d` is a day in it.
     */
    public function zone(): DateTimeZone
    {
        return new DateTimeZone($this->timezone);
    }

    /**
     * @param list<string>|null $tags
     * @param Member|null       $member whose own calendar it shows
     */
    public function month(Period $period, ?array $tags = null, int $lanes = 3, ?Member $member = null): MonthGrid
    {
        return MonthGrid::of($period, $this->items->of($period, $tags, $member), $lanes);
    }

    /**
     * @param list<string>|null $tags
     * @param ?string           $shared `hosts`, `venues` or `tags`: the day shared between them
     * @param Member|null       $member whose own calendar it shows
     */
    public function time(Period $period, ?array $tags = null, ?string $shared = null, int $firstHour = 0, int $lastHour = 24, ?Member $member = null): TimeGrid
    {
        $items = $this->items->of($period, $tags, $member);

        return TimeGrid::of($period, $items, null === $shared ? null : $this->resources($shared, $items, null, $tags), $firstHour, $lastHour);
    }

    /**
     * @param list<string>|null $tags
     * @param Member|null       $member whose own calendar it shows
     */
    public function agenda(Period $period, ?array $tags = null, ?Member $member = null): Agenda
    {
        return Agenda::of($this->items->of($period, $tags, $member));
    }

    /**
     * The columns of a shared day: the hosts and the tags the items have, by their label — only the tags asked for, when
     * the items are filtered by tag —; every venue of the calendar.
     *
     * @param list<Item>        $items
     * @param list<string>|null $tags  the tags the items are filtered by
     */
    public function resources(string $shared, array $items, ?string $other = null, ?array $tags = null): Resources
    {
        $venues = self::VENUES === $shared ? $this->venues->all() : [];
        $itemTags = array_merge(...array_map(static fn (Item $item): array => $item->tags, $items));

        return match ($shared) {
            self::HOSTS => Resources::hosts($this->hostLabels(array_merge(...array_map(static fn (Item $item): array => $item->hosts, $items))), $other),
            self::VENUES => Resources::venues(array_combine(
                array_map(static fn (Venue $venue): string => $venue->id, $venues),
                array_map(static fn (Venue $venue): string => $venue->label(), $venues),
            ), $other),
            self::TAGS => Resources::tags($this->byThemselves(null === $tags || [] === $tags ? $itemTags : array_intersect($itemTags, $tags)), $other),
            default => throw new InvalidArgumentException(\sprintf('A day is shared between hosts, venues or tags, not "%s".', $shared)),
        };
    }

    /**
     * @param list<string> $hosts
     *
     * @return array<string, string>
     */
    private function hostLabels(array $hosts): array
    {
        return array_merge($this->byThemselves($hosts), array_intersect_key($this->hostLabels?->labels($hosts) ?? [], array_flip($hosts)));
    }

    /**
     * The words, sorted, each its own label: twice the same is one.
     *
     * @param array<string> $words
     *
     * @return array<string, string>
     */
    private function byThemselves(array $words): array
    {
        sort($words);

        return array_combine($words, $words);
    }
}
