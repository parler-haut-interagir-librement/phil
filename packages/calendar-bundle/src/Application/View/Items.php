<?php

declare(strict_types=1);

namespace Phil\CalendarBundle\Application\View;

use Phil\CalendarBundle\Domain\ValueObject\Member;

/**
 * Every source's items of a period, in one list: by start, whole days first, then by title. For a member, the
 * calendar's events are those of their own calendar; the other sources stay as they are.
 */
final readonly class Items
{
    /**
     * @param iterable<ItemSource> $sources
     */
    public function __construct(
        private iterable $sources,
    ) {
    }

    /**
     * @param list<string>|null $tags   only the items with one of these tags
     * @param Member|null       $member whose own calendar the events come from
     *
     * @return list<Item>
     */
    public function of(Period $period, ?array $tags = null, ?Member $member = null): array
    {
        $items = [];

        foreach ($this->sources as $source) {
            $found = $member instanceof Member && $source instanceof EventItems ? $source->itemsOf($member, $period->from, $period->until) : $source->items($period->from, $period->until);

            foreach ($found as $item) {
                if (null === $tags || [] !== array_intersect($tags, $item->tags)) {
                    $items[] = $item;
                }
            }
        }

        usort($items, static fn (Item $a, Item $b): int => [$a->start, !$a->allDay, $a->title] <=> [$b->start, !$b->allDay, $b->title]);

        return $items;
    }
}
