<?php

declare(strict_types=1);

namespace Phil\CalendarBundle\Application\View;

/**
 * A list of items grouped by the days they cover: the items of one day, then those of that day and the next, then
 * the days after — by first day, then by last day, the shortest first; in a group, by time, then title.
 *
 * September 1 · September 1 and 2 · September 2 · September 2–3 · September 2–4 · September 3–4 · September 4
 */
final readonly class Agenda
{
    /**
     * @param list<AgendaGroup> $groups
     */
    private function __construct(
        public array $groups,
    ) {
    }

    /**
     * @param list<Item> $items
     */
    public static function of(array $items): self
    {
        $byDays = [];

        foreach ($items as $item) {
            $byDays[$item->firstDay()->format('Ymd').$item->lastDay()->format('Ymd')][] = $item;
        }

        ksort($byDays);
        $groups = [];

        foreach ($byDays as $days) {
            $order = static fn (Item $item): array => [!$item->allDay, $item->start, $item->title];
            usort($days, static fn (Item $a, Item $b): int => $order($a) <=> $order($b));
            $groups[] = new AgendaGroup($days[0]->firstDay(), $days[0]->lastDay(), $days);
        }

        return new self($groups);
    }

    /**
     * Every item once, by group: the posters of the period.
     *
     * @return list<Item>
     */
    public function items(): array
    {
        return array_merge(...array_map(static fn (AgendaGroup $group): array => $group->items, $this->groups));
    }
}
