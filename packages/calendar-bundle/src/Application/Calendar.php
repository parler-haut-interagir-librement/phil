<?php

declare(strict_types=1);

namespace Phil\CalendarBundle\Application;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use LogicException;
use Phil\CalendarBundle\Domain\Model\Event;
use Phil\CalendarBundle\Domain\Model\EventHost;
use Phil\CalendarBundle\Domain\Repository\EventRepository;
use Phil\CalendarBundle\Domain\ValueObject\Member;
use Phil\CalendarBundle\Domain\ValueObject\Occurrence;
use Phil\Recurrence\RecurrenceRule;
use Psr\Log\LoggerInterface;

/**
 * The calendar: events scheduled, changed from drafts and removed, their hosts, and the occurrences of a period — of a
 * host, with a tag — in order.
 *
 * With an `EventTagging`, an event's tags are those of a taxonomy shared with the site: the event keeps their names.
 *
 * The occurrences it unrolls are counted against an `ExpansionBudget`: the one shared by a request when it is given,
 * else one of the default limits for each call. Past it, the events left are not read, the lists hold what was read,
 * and the stop is logged once, as a warning.
 */
final readonly class Calendar
{
    /** The occurrences of one event read at most for a period. */
    public const int PER_EVENT = 1000;

    public function __construct(
        private EventRepository $events,
        private ?EventTagging $tagging = null,
        private ?ExpansionBudget $budget = null,
        private ?LoggerInterface $logger = null,
    ) {
    }

    /**
     * Whether the budget of the request stopped a list short since it was last reset: a view says some events are not
     * shown.
     */
    public function isCut(): bool
    {
        return $this->budget?->isExhausted() ?? false;
    }

    /**
     * A new event from a draft; a member's proposal, kept out of the calendar, when a member proposes it.
     */
    public function schedule(EventDraft $draft, ?Member $proposedBy = null): Event
    {
        [$start, $end, $timezone] = $this->times($draft);
        $event = Event::schedule((string) $draft->title, $start, $end, $draft->allDay, $timezone);

        if ($proposedBy instanceof Member) {
            $event->proposeBy($proposedBy);
        }

        $this->fill($event, $draft);

        return $event;
    }

    public function apply(Event $event, EventDraft $draft): void
    {
        [$start, $end, $timezone] = $this->times($draft);
        $event->rename((string) $draft->title);
        $event->reschedule($start, $end, $draft->allDay, $timezone);
        $this->fill($event, $draft);
    }

    /**
     * Removes the event, its hosts, and its tags from the taxonomy.
     */
    public function remove(Event $event): void
    {
        $this->events->remove($event);
        $this->tagging?->forget($event);
    }

    /**
     * Gives each event the names of its tags in the taxonomy again — after tags were renamed or merged there; an
     * event the taxonomy knows no tag of, tagged before it came, gives it its own words. Every event is read: run it
     * from the console, not on a page.
     *
     * @return int how many events changed
     */
    public function refreshTags(): int
    {
        if (!$this->tagging instanceof EventTagging) {
            return 0;
        }

        $changed = 0;

        foreach ($this->events->all() as $event) {
            $names = $this->tagging->tagsOf($event);

            if ([] === $names && [] !== $event->tags()) {
                // Tagged before the taxonomy came: its words become the taxonomy's tags, and are kept.
                $names = $this->tagging->tag($event, $event->tags());
            }

            if ($names !== $event->tags()) {
                $event->tag($names);
                $this->events->save($event);
                ++$changed;
            }
        }

        return $changed;
    }

    public function host(Event $event, string $kind, string $reference): void
    {
        if (!$this->hostOf($event, $kind, $reference) instanceof EventHost) {
            $this->events->saveHost(new EventHost($event, $kind, $reference));
        }
    }

    public function unhost(Event $event, string $kind, string $reference): void
    {
        $host = $this->hostOf($event, $kind, $reference);

        if ($host instanceof EventHost) {
            $this->events->removeHost($host);
        }
    }

    /**
     * The occurrences that overlap a period, of a host and with a tag when they are given, by their start; those of
     * cancelled events included, for the page to say so.
     *
     * @return list<Occurrence>
     */
    public function occurrences(DateTimeInterface $from, DateTimeInterface $through, ?string $hostKind = null, ?string $hostReference = null, ?string $tag = null): array
    {
        $occurrences = [];
        $budget = $this->budget ?? new ExpansionBudget();

        foreach ($this->events->overlapping($from, $through, $hostKind, $hostReference) as $event) {
            if (null !== $tag && !\in_array($tag, $event->tags(), true)) {
                continue;
            }

            if (!$budget->admits($this->repeats($event))) {
                break;
            }

            $found = $event->occurrences($from, $through, $budget->limit(self::PER_EVENT));
            $budget->spend(\count($found));
            array_push($occurrences, ...$found);
        }

        $this->report($budget);

        usort($occurrences, static fn (Occurrence $a, Occurrence $b): int => [$a->start, $a->event->title()] <=> [$b->start, $b->event->title()]);

        return $occurrences;
    }

    /**
     * The events with an occurrence that overlaps a period, of a host and with a tag when they are given, by their
     * start: each read only as far as its first occurrence in the period, for a feed that gives whole events.
     *
     * @return list<Event>
     */
    public function events(DateTimeInterface $from, DateTimeInterface $through, ?string $hostKind = null, ?string $hostReference = null, ?string $tag = null): array
    {
        $events = [];
        $budget = $this->budget ?? new ExpansionBudget();

        foreach ($this->events->overlapping($from, $through, $hostKind, $hostReference) as $event) {
            if (null !== $tag && !\in_array($tag, $event->tags(), true)) {
                continue;
            }

            if (!$budget->admits($this->repeats($event))) {
                break;
            }

            $found = \count($event->occurrences($from, $through, $budget->limit(1)));
            $budget->spend($found);

            if (0 < $found) {
                $events[] = $event;
            }
        }

        $this->report($budget);

        return $events;
    }

    private function repeats(Event $event): bool
    {
        return $event->rule() instanceof RecurrenceRule || [] !== $event->addedDates();
    }

    private function report(ExpansionBudget $budget): void
    {
        if ($budget->shouldReport()) {
            $this->logger?->warning('The calendar stopped reading events at its budget: {occurrences} of {max_occurrences} occurrences, {expansions} of {max_expansions} repeating events.', [
                'occurrences' => $budget->occurrences(),
                'max_occurrences' => $budget->maxOccurrences,
                'expansions' => $budget->expansions(),
                'max_expansions' => $budget->maxExpansions,
            ]);
        }
    }

    private function fill(Event $event, EventDraft $draft): void
    {
        $event->describe($draft->description, $draft->location, $draft->url);
        $event->placeAt($draft->venue);
        $event->illustrate($draft->image);
        $event->repeat($draft->rule);
        $event->tag($draft->tags);
        $event->limitTo($draft->capacity);

        $this->events->save($event);

        // The taxonomy once the event is saved: a refused save leaves it untouched.
        if ($this->tagging instanceof EventTagging) {
            $names = $this->tagging->tag($event, $event->tags());

            if ($names !== $event->tags()) {
                $event->tag($names);
                $this->events->save($event);
            }
        }
    }

    private function hostOf(Event $event, string $kind, string $reference): ?EventHost
    {
        return array_find($this->events->hostsOf($event), static fn (EventHost $host): bool => $host->is($kind, $reference));
    }

    /**
     * The draft's times on the wall clock of its time zone: a form gives them without one.
     *
     * @return array{DateTimeImmutable, DateTimeImmutable, string}
     */
    private function times(EventDraft $draft): array
    {
        if (!$draft->start instanceof DateTimeImmutable || !$draft->end instanceof DateTimeImmutable || null === $draft->timezone) {
            throw new LogicException('A draft is scheduled once checked: with its start, its end and its time zone.');
        }

        $zone = new DateTimeZone($draft->timezone);
        $wall = static fn (DateTimeImmutable $time): DateTimeImmutable => new DateTimeImmutable($time->format('Y-m-d H:i:s'), $zone);

        return [$wall($draft->start), $wall($draft->end), $draft->timezone];
    }
}
