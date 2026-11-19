<?php

declare(strict_types=1);

namespace Phil\EditorialCalendar\Review;

use DateTimeImmutable;
use Phil\EditorialCalendar\EditorialCalendar;
use Phil\EditorialCalendar\Exception\InvalidItem;
use Phil\EditorialCalendar\ItemState;
use Phil\EditorialCalendar\Moment;
use Phil\EditorialCalendar\PlannedItem;
use Phil\EditorialCalendar\View\CalendarView;
use Phil\EditorialCalendar\View\Entry;

/**
 * Finds the conflicts and gaps of a calendar between two moments: the same content planned twice in one language,
 * items planned while still ideas or drafts, publications closer than the rules allow, and stretches without any. A
 * batch counts as one publication. It keeps no state between calls.
 */
final readonly class Review
{
    public function __construct(
        private ReviewRules $rules = new ReviewRules(),
    ) {
    }

    /**
     * The issues from `$from`, included, to `$to`, excluded: repeated content, unready items, publications too close,
     * then gaps, each kind in the order of its moments.
     *
     * @return list<Issue>
     *
     * @throws InvalidItem when `$to` is not after `$from`
     */
    public function issues(EditorialCalendar $calendar, DateTimeImmutable $from, DateTimeImmutable $to): array
    {
        // The view reads no transitions: every entry is an item or a batch, a publication.
        $entries = new CalendarView()->list($calendar, $from, $to)->entries;

        return [
            ...$this->duplicates($entries),
            ...$this->unready($entries),
            ...$this->tooClose($entries),
            ...$this->gaps($entries, Moment::utc($from), Moment::utc($to)),
        ];
    }

    /**
     * @param list<Entry> $entries
     *
     * @return list<Issue>
     */
    private function duplicates(array $entries): array
    {
        /** @var array<string, list<array{string, DateTimeImmutable}>> $groups */
        $groups = [];

        foreach ($entries as $entry) {
            foreach ($entry->items as $item) {
                $groups[$item->content->toString().' '.$item->locale][] = [$item->id, $entry->at];
            }
        }

        $issues = [];

        foreach ($groups as $group) {
            if (\count($group) > 1) {
                $issues[] = new Issue(IssueKind::DuplicateContent, $group[0][1], $group[\count($group) - 1][1], array_column($group, 0));
            }
        }

        return $issues;
    }

    /**
     * @param list<Entry> $entries
     *
     * @return list<Issue>
     */
    private function unready(array $entries): array
    {
        $issues = [];

        foreach ($entries as $entry) {
            foreach ($entry->items as $item) {
                if (ItemState::Idea === $item->state || ItemState::Drafting === $item->state) {
                    $issues[] = new Issue(IssueKind::NotReady, $entry->at, $entry->at, [$item->id]);
                }
            }
        }

        return $issues;
    }

    /**
     * @param list<Entry> $entries
     *
     * @return list<Issue>
     */
    private function tooClose(array $entries): array
    {
        $issues = [];
        $count = \count($entries);

        for ($i = 1; $i < $count; ++$i) {
            [$before, $after] = [$entries[$i - 1], $entries[$i]];

            if ($after->at->getTimestamp() - $before->at->getTimestamp() < $this->rules->minimumSpacing) {
                $issues[] = new Issue(IssueKind::TooClose, $before->at, $after->at, [...$this->ids($before), ...$this->ids($after)]);
            }
        }

        return $issues;
    }

    /**
     * @param list<Entry> $entries
     *
     * @return list<Issue>
     */
    private function gaps(array $entries, DateTimeImmutable $from, DateTimeImmutable $to): array
    {
        if (null === $this->rules->maximumGap) {
            return [];
        }

        $issues = [];
        $moments = [$from, ...array_map(static fn (Entry $entry): DateTimeImmutable => $entry->at, $entries), $to];
        $count = \count($moments);

        for ($i = 1; $i < $count; ++$i) {
            if ($moments[$i]->getTimestamp() - $moments[$i - 1]->getTimestamp() > $this->rules->maximumGap) {
                $issues[] = new Issue(IssueKind::Gap, $moments[$i - 1], $moments[$i]);
            }
        }

        return $issues;
    }

    /**
     * @return list<string>
     */
    private function ids(Entry $entry): array
    {
        return array_map(static fn (PlannedItem $item): string => $item->id, $entry->items);
    }
}
