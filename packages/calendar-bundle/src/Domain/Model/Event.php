<?php

declare(strict_types=1);

namespace Phil\CalendarBundle\Domain\Model;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Doctrine\ORM\Mapping as ORM;
use Exception;
use Override;
use Phil\CalendarBundle\Domain\Exception\InvalidEvent;
use Phil\CalendarBundle\Domain\ValueObject\Member;
use Phil\CalendarBundle\Domain\ValueObject\Occurrence;
use Phil\DoctrineBehaviours\Contract\Translatable;
use Phil\LocaleDimensions\Locale;
use Phil\Recurrence\Recurrence;
use Phil\Recurrence\RecurrenceDates;
use Phil\Recurrence\RecurrenceRule;
use Phil\Url\Exception\InvalidUrl;
use Phil\Url\Url;
use Symfony\Component\Uid\Uuid;

/**
 * Something that takes place: a title, a description, a place and a page; a start and an end in a time zone, or whole
 * days; the rule it repeats by, the dates added to it and the occurrences taken away; its tags; cancelled or not. Its
 * hosts are `EventHost`s.
 *
 * Times are kept in UTC, in columns of type `datetime_utc_immutable`, with the time zone they were given in, and given
 * back in it. `seriesEndsAt` is when its last
 * occurrence ends — `null` for a rule without end — for the repository to find the events of a period.
 */
#[ORM\Entity]
#[ORM\Table(name: 'phil_calendar_event')]
#[ORM\Index(columns: ['starts_at', 'series_ends_at'])]
final class Event implements Translatable
{
    private const string UTC = 'UTC';

    /** The state of an event shown in the calendar. */
    public const string PUBLISHED = 'published';

    /** The state of an event a member proposed, waiting for its approval. */
    public const string PROPOSED = 'proposed';

    /** The state of a proposal turned down. */
    public const string REFUSED = 'refused';

    /** The pages of their own an event's occurrences have at most. */
    public const int DATE_LINKS = 500;

    /** The dates added to an event at most, as many as one `RDATE` line holds. */
    public const int ADDED_DATES = RecurrenceDates::MAX_DATES;

    #[ORM\Column(name: 'description', type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(name: 'location', length: 255, nullable: true)]
    private ?string $location = null;

    #[ORM\Column(name: 'url', length: 2048, nullable: true)]
    private ?string $url = null;

    #[ORM\ManyToOne(targetEntity: Venue::class)]
    #[ORM\JoinColumn(name: 'venue', nullable: true, onDelete: 'SET NULL')]
    private ?Venue $venue = null;

    #[ORM\Column(name: 'image', length: 2048, nullable: true)]
    private ?string $image = null;

    #[ORM\Column(name: 'rule', type: 'phil_rrule', nullable: true)]
    private ?RecurrenceRule $rule = null;

    /** @var list<string> occurrences taken away, by their start in UTC */
    #[ORM\Column(name: 'exceptions', type: 'json')]
    private array $exceptions = [];

    /** @var ?list<string> dates added to the occurrences, `RDATE`, by their start in UTC, in order; null for none */
    #[ORM\Column(name: 'added_dates', type: 'json', nullable: true)]
    private ?array $addedDates = null;

    /** @var list<string> */
    #[ORM\Column(name: 'tags', type: 'json')]
    private array $tags = [];

    #[ORM\Column(name: 'cancelled')]
    private bool $cancelled = false;

    /** @var array<string, string> a page of its own for an occurrence — its tickets —, by the occurrence's start in UTC */
    #[ORM\Column(name: 'date_links', type: 'json')]
    private array $dateLinks = [];

    /** How many people can attend each occurrence; null for no limit. */
    #[ORM\Column(name: 'capacity', nullable: true)]
    private ?int $capacity = null;

    /** Where the event stands: published, or proposed by a member and not yet approved, or refused. */
    #[ORM\Column(name: 'proposal_state', length: 16, options: ['default' => self::PUBLISHED])]
    private string $proposalState = self::PUBLISHED;

    /** The member who proposed it, `kind:reference`. */
    #[ORM\Column(name: 'proposed_by', length: 129, nullable: true)]
    private ?string $proposedBy = null;

    #[ORM\Column(name: 'series_ends_at', type: 'datetime_utc_immutable', nullable: true)]
    private ?DateTimeImmutable $seriesEndsAt = null;

    #[ORM\Version]
    #[ORM\Column(name: 'version', type: 'integer')]
    private int $version = 1;

    /**
     * The recurrence last built, with what it was built from: its rule, start, time zone, exceptions and added dates. It
     * is built again once one of them changes — a change of the event, or the event read again —, not on every read.
     *
     * @var array{}|array{from: array{?RecurrenceRule, int, string, list<string>, ?list<string>}, recurrence: Recurrence}
     */
    private array $recurrenceOf = [];

    /**
     * The end of the rule's series last computed, with what it was computed from; the dates added are not part of it.
     *
     * @var array{}|array{from: array{?string, int, int, bool, string, list<string>}, end: ?DateTimeImmutable}
     */
    private array $seriesEndOf = [];

    private function __construct(
        #[ORM\Id]
        #[ORM\Column(name: 'id', length: 36, options: ['fixed' => true])]
        public readonly string $id,
        #[ORM\Column(name: 'title', length: 200)]
        private string $title,
        #[ORM\Column(name: 'starts_at', type: 'datetime_utc_immutable')]
        private DateTimeImmutable $startsAt,
        #[ORM\Column(name: 'ends_at', type: 'datetime_utc_immutable')]
        private DateTimeImmutable $endsAt,
        #[ORM\Column(name: 'all_day')]
        private bool $allDay,
        #[ORM\Column(name: 'timezone', length: 64)]
        private string $timezone,
    ) {
    }

    /**
     * @param DateTimeInterface $start in the event's time zone, or a day at midnight for a whole-day event
     * @param DateTimeInterface $end   after the start; the last day, included, for a whole-day event
     */
    public static function schedule(string $title, DateTimeInterface $start, DateTimeInterface $end, bool $allDay = false, ?string $timezone = null): self
    {
        $zone = self::zone($timezone ?? $start->getTimezone()->getName());
        $event = new self(Uuid::v7()->toRfc4122(), self::checkedTitle($title), new DateTimeImmutable(), new DateTimeImmutable(), $allDay, $zone->getName());
        $event->reschedule($start, $end, $allDay, $zone->getName());

        return $event;
    }

    public function title(): string
    {
        return $this->title;
    }

    public function rename(string $title): void
    {
        $this->title = self::checkedTitle($title);
    }

    public function description(): ?string
    {
        return $this->description;
    }

    public function location(): ?string
    {
        return $this->location;
    }

    public function url(): ?string
    {
        return $this->url;
    }

    /**
     * What the event is, where, and its page: an `http` or `https` address.
     */
    public function describe(?string $description, ?string $location = null, ?string $url = null): void
    {
        $location = self::text($location, 255, 'location');
        $url = self::text($url, 2048, 'page');

        $url = null === $url ? null : $this->address($url, 'The page of an event is an http or https address.');

        $this->description = self::text($description, 65535, 'description');
        $this->location = $location;
        $this->url = $url;
    }

    /**
     * Where it takes place, when the calendar knows the venue; `location()` stays for a place said in words.
     */
    public function venue(): ?Venue
    {
        return $this->venue;
    }

    public function placeAt(?Venue $venue): void
    {
        $this->venue = $venue;
    }

    /**
     * Its poster or picture: an `http` or `https` address, or a path from the site's root — never one a browser reads as
     * another host's, `//host/x.png` or `/\\host/x.png`.
     */
    public function image(): ?string
    {
        return $this->image;
    }

    public function illustrate(?string $image): void
    {
        $image = self::text($image, 2048, 'image');

        if (null !== $image && !$this->isRootPath($image)) {
            $image = $this->address($image, 'The image of an event is an http or https address, or a path from the root.');
        }

        $this->image = $image;
    }

    public function timezone(): DateTimeZone
    {
        return new DateTimeZone($this->timezone);
    }

    public function start(): DateTimeImmutable
    {
        return $this->local($this->startsAt);
    }

    public function end(): DateTimeImmutable
    {
        return $this->local($this->endsAt);
    }

    public function isAllDay(): bool
    {
        return $this->allDay;
    }

    /**
     * Moves the event: a timed one ends after it starts; a whole-day one, given its first and last days, ends on its
     * last day or later.
     */
    public function reschedule(DateTimeInterface $start, DateTimeInterface $end, bool $allDay = false, ?string $timezone = null): void
    {
        $zone = self::zone($timezone ?? $this->timezone);
        $start = DateTimeImmutable::createFromInterface($start)->setTimezone($zone);
        $end = DateTimeImmutable::createFromInterface($end)->setTimezone($zone);

        if ($allDay) {
            $start = $start->setTime(0, 0);
            $end = $end->setTime(0, 0);
        }

        if ($allDay ? $end < $start : $end <= $start) {
            throw InvalidEvent::because('An event ends after it starts; a whole-day event, on its first day or later.');
        }

        $this->timezone = $zone->getName();
        $this->allDay = $allDay;
        $this->startsAt = $start->setTimezone(new DateTimeZone(self::UTC));
        $this->endsAt = $end->setTimezone(new DateTimeZone(self::UTC));
        $this->keepAddedDatesAfter($this->instant($start));
        $this->seriesEndsAt = $this->seriesEnd();
    }

    public function rule(): ?RecurrenceRule
    {
        return $this->rule;
    }

    /**
     * Repeats the event by a rule from its start, or stops it repeating. The occurrences taken away are kept while the
     * rule stays the same — an edit that leaves the schedule alone keeps them —, and forgotten with a new rule.
     */
    public function repeat(?RecurrenceRule $rule): void
    {
        if ((string) $rule !== (string) $this->rule) {
            $this->exceptions = [];
            $this->dateLinks = [];
        }

        $this->rule = $rule;
        $this->seriesEndsAt = $this->seriesEnd();
    }

    /**
     * Adds an occurrence that starts at a moment, as `RDATE` does: after the event's start, of the event's length, kept
     * whatever rule the event repeats by — or none: an event of a few dates. A whole-day event adds the day the moment
     * falls on in its time zone. `except()` still takes it away.
     *
     * @throws InvalidEvent when the moment is not after the start, or the event has `ADDED_DATES` dates already
     */
    public function addDate(DateTimeInterface $occurrence): void
    {
        $moment = DateTimeImmutable::createFromInterface($occurrence)->setTimezone($this->timezone());
        $moment = $this->allDay ? $moment->setTime(0, 0) : $moment;

        if ($moment <= $this->start()) {
            throw InvalidEvent::because('A date added to an event is after its start.');
        }

        $instant = $this->instant($moment);
        $added = $this->addedDates ?? [];

        if (\in_array($instant, $added, true)) {
            return;
        }

        if (\count($added) >= self::ADDED_DATES) {
            throw InvalidEvent::because(\sprintf('An event has %d dates added at most.', self::ADDED_DATES));
        }

        $added[] = $instant;
        sort($added, \SORT_STRING);
        $this->addedDates = $added;
        $this->seriesEndsAt = $this->seriesEnd();
    }

    /**
     * Takes back a date added; one that was not added is left alone.
     */
    public function removeDate(DateTimeInterface $occurrence): void
    {
        $instant = $this->instant($occurrence);
        $added = array_values(array_filter($this->addedDates ?? [], static fn (string $date): bool => $date !== $instant));
        $this->addedDates = [] === $added ? null : $added;
        $this->seriesEndsAt = $this->seriesEnd();
    }

    /**
     * @return list<DateTimeImmutable> in the event's time zone, in order
     */
    public function addedDates(): array
    {
        return array_map(fn (string $instant): DateTimeImmutable => $this->local(new DateTimeImmutable($instant, new DateTimeZone(self::UTC))), $this->addedDates ?? []);
    }

    /**
     * Takes away the occurrence that starts at a moment, as `EXDATE` does.
     */
    public function except(DateTimeInterface $occurrence): void
    {
        $instant = $this->instant($occurrence);

        if (!\in_array($instant, $this->exceptions, true)) {
            $this->exceptions[] = $instant;
        }
    }

    /**
     * @return list<DateTimeImmutable> in the event's time zone
     */
    public function exceptions(): array
    {
        return array_map(fn (string $instant): DateTimeImmutable => $this->local(new DateTimeImmutable($instant, new DateTimeZone(self::UTC))), $this->exceptions);
    }

    /**
     * Gives the occurrence that starts at a moment a page of its own — where its tickets are sold —, or takes it away
     * with null; the other occurrences keep the event's page. The address is read as the event's page is.
     */
    public function linkDate(DateTimeInterface $occurrence, ?string $url): void
    {
        $instant = $this->instant($occurrence);
        $url = self::text($url, 2048, 'page');

        if (null === $url) {
            unset($this->dateLinks[$instant]);

            return;
        }

        if (!isset($this->dateLinks[$instant]) && \count($this->dateLinks) >= self::DATE_LINKS) {
            throw InvalidEvent::because(\sprintf('An event gives its own page to %d occurrences at most.', self::DATE_LINKS));
        }

        $this->dateLinks[$instant] = $this->address($url, 'The page of an occurrence is an http or https address.');
        ksort($this->dateLinks, \SORT_STRING);
    }

    /**
     * The pages of their own, by the start of their occurrence in the event's time zone, in order.
     *
     * @return array<string, string> by the start, `Y-m-d H:i`
     */
    public function dateLinks(): array
    {
        $links = [];

        foreach ($this->dateLinks as $instant => $url) {
            $links[$this->local(new DateTimeImmutable($instant, new DateTimeZone(self::UTC)))->format('Y-m-d H:i')] = $url;
        }

        return $links;
    }

    /**
     * The page of the occurrence that starts at a moment: its own, or the event's.
     */
    public function urlOn(DateTimeInterface $occurrence): ?string
    {
        return $this->dateLinks[$this->instant($occurrence)] ?? $this->url;
    }

    public function capacity(): ?int
    {
        return $this->capacity;
    }

    /**
     * How many people can attend each occurrence, 1 or more; null for no limit.
     */
    public function limitTo(?int $capacity): void
    {
        if (null !== $capacity && ($capacity < 1 || $capacity > 1_000_000)) {
            throw InvalidEvent::because('The capacity of an event is 1 to 1000000 people, or none.');
        }

        $this->capacity = $capacity;
    }

    /**
     * Makes the event a member's proposal, not shown until it is approved.
     */
    public function proposeBy(Member $member): void
    {
        if (null !== $this->proposedBy) {
            throw InvalidEvent::because('The event is already a proposal.');
        }

        $this->proposedBy = $member->toString();
        $this->proposalState = self::PROPOSED;
    }

    public function proposedBy(): ?Member
    {
        return null === $this->proposedBy ? null : Member::fromString($this->proposedBy);
    }

    /**
     * No longer names who proposed it, when their data is erased; its state stays.
     */
    public function forgetProposer(): void
    {
        $this->proposedBy = null;
    }

    /**
     * The state of the proposal, for the workflow's marking store.
     */
    public function getProposalState(): string
    {
        return $this->proposalState;
    }

    /**
     * Set by the workflow `phil_calendar_proposal` only, through its transitions.
     *
     * @internal
     */
    public function setProposalState(string $state): void
    {
        if (!\in_array($state, [self::PUBLISHED, self::PROPOSED, self::REFUSED], true)) {
            throw InvalidEvent::because(\sprintf('An event is published, proposed or refused, not "%s".', $state));
        }

        $this->proposalState = $state;
    }

    public function isPublished(): bool
    {
        return self::PUBLISHED === $this->proposalState;
    }

    public function cancel(): void
    {
        $this->cancelled = true;
    }

    public function restore(): void
    {
        $this->cancelled = false;
    }

    public function isCancelled(): bool
    {
        return $this->cancelled;
    }

    /**
     * @param list<string> $tags each once, trimmed, the empty ones and those over 100 characters left out
     */
    public function tag(array $tags): void
    {
        $this->tags = array_values(array_unique(array_filter(array_map(trim(...), $tags), static fn (string $tag): bool => '' !== $tag && mb_strlen($tag) <= 100)));
    }

    /**
     * @return list<string>
     */
    public function tags(): array
    {
        return $this->tags;
    }

    public function version(): int
    {
        return $this->version;
    }

    public function seriesEndsAt(): ?DateTimeImmutable
    {
        return $this->seriesEndsAt;
    }

    /**
     * The occurrences that overlap a period, in order, at most so many — at least one; one for an event that does not
     * repeat.
     *
     * @return list<Occurrence>
     */
    public function occurrences(DateTimeInterface $from, DateTimeInterface $through, int $limit = 1000): array
    {
        $occurrences = [];

        // An occurrence that overlaps the period starts at most its length before it: the rule is read from there, not
        // from its first occurrence, so that a period costs the same however old the event. The length is counted in
        // days, one more for the time of day and one for a change of time; the occurrences of those spare days — at
        // most one a day — are the only ones read and left out.
        $length = $this->lengthInDays();
        $time = $this->endTime();
        $reach = $length + 2;
        $earliest = DateTimeImmutable::createFromInterface($from)->modify(\sprintf('-%d days', $reach));

        foreach ($this->recurrence()->between($earliest, $through, $limit + $reach + 1) as $start) {
            $end = $this->endOf($start, $length, $time);

            if (($this->allDay ? $end->modify('+1 day') : $end) > $from) {
                $occurrences[] = new Occurrence($this, $start, $end);

                if (\count($occurrences) >= $limit) {
                    return $occurrences;
                }
            }
        }

        return $occurrences;
    }

    private function recurrence(): Recurrence
    {
        $from = [$this->rule, $this->startsAt->getTimestamp(), $this->timezone, $this->exceptions, $this->addedDates];

        if (($this->recurrenceOf['from'] ?? null) !== $from) {
            $this->recurrenceOf = ['from' => $from, 'recurrence' => Recurrence::of($this->start(), $this->rule ?? RecurrenceRule::parse('FREQ=DAILY;COUNT=1'), $this->exceptions(), $this->addedDates())];
        }

        return $this->recurrenceOf['recurrence'];
    }

    /**
     * The end of an occurrence: as many days after its start as the event's end is after its start, at the same time
     * of day — on the wall clock, so that a change of time between the two neither adds nor takes an hour.
     *
     * @param int       $days as `lengthInDays()` counts them
     * @param list<int> $time the end's time of day, as `endTime()` gives it
     */
    private function endOf(DateTimeImmutable $start, int $days, array $time): DateTimeImmutable
    {
        [$hour, $minute, $second] = $time;
        $end = $start->modify(\sprintf('+%d days', $days))->setTime($hour, $minute, $second);
        // A date added at another time of day than the event's start keeps the event's length.
        $shift = $this->secondOfDay($start) - $this->secondOfDay($this->start());

        return 0 === $shift ? $end : $end->modify(\sprintf('%+d seconds', $shift));
    }

    private function secondOfDay(DateTimeImmutable $moment): int
    {
        [$hour, $minute, $second] = array_map(intval(...), explode(':', $moment->format('H:i:s')));

        return 3600 * $hour + 60 * $minute + $second;
    }

    /**
     * The end's time of day on the wall clock: hours, minutes and seconds.
     *
     * @return list<int>
     */
    private function endTime(): array
    {
        return array_map(intval(...), explode(':', $this->end()->format('H:i:s')));
    }

    /**
     * How many calendar days the event's end is after its start, on the wall clock of its time zone.
     */
    private function lengthInDays(): int
    {
        $utc = new DateTimeZone(self::UTC);

        return new DateTimeImmutable($this->start()->format('Y-m-d'), $utc)->diff(new DateTimeImmutable($this->end()->format('Y-m-d'), $utc))->days;
    }

    /**
     * Whether an image is a path from the site's root: one slash, then neither a slash nor a backslash — browsers read
     * a backslash as a slash, so `/\\host` is another host —, and no backslash anywhere.
     */
    /**
     * An address read as a browser reads it, by `phil/url`, and kept normalised: an `http` or `https` address on a
     * public host.
     */
    private function address(string $text, string $refusal): string
    {
        try {
            return Url::fromString($text)->toString();
        } catch (InvalidUrl $invalidUrl) {
            throw InvalidEvent::because($refusal, $invalidUrl);
        }
    }

    private function isRootPath(string $image): bool
    {
        return 1 === preg_match('#^/(?![/\\\\])#', $image) && !str_contains($image, '\\');
    }

    /**
     * The start of the last occurrence. A rule that ends on a date, with no count, is read back from its end — its last
     * year, then two, four, and so on —, so that a series ending far away costs its last years, not all of them.
     */
    private function lastStart(): DateTimeImmutable
    {
        $rule = $this->rule;
        // The rule alone: the dates added end the series on their own, in `seriesEnd()`.
        $recurrence = Recurrence::of($this->start(), $rule ?? RecurrenceRule::parse('FREQ=DAILY;COUNT=1'), $this->exceptions());

        if ($rule instanceof RecurrenceRule && null === $rule->count && $rule->until instanceof DateTimeImmutable) {
            for ($years = 1;; $years *= 2) {
                $from = $rule->until->modify(\sprintf('-%d years', $years));
                $starts = $recurrence->between($from, null, \PHP_INT_MAX);

                if ([] !== $starts || $from <= $this->start()) {
                    return array_last($starts) ?? $this->start();
                }
            }
        }

        $last = $this->start();

        foreach ($recurrence->occurrences() as $start) {
            $last = $start;
        }

        return $last;
    }

    /**
     * When the last occurrence ends, in UTC — that of the rule or that of the last date added —; `null` when the rule
     * has no end.
     */
    private function seriesEnd(): ?DateTimeImmutable
    {
        // The end of the rule is computed once for a schedule: an edit that saves the schedule unchanged — rescheduled,
        // then repeated by the same rule — reads the series once, and adding dates does not read it again.
        $from = [$this->rule?->__toString(), $this->startsAt->getTimestamp(), $this->endsAt->getTimestamp(), $this->allDay, $this->timezone, $this->exceptions];

        if (($this->seriesEndOf['from'] ?? null) !== $from) {
            $this->seriesEndOf = ['from' => $from, 'end' => $this->computedSeriesEnd()];
        }

        $end = $this->seriesEndOf['end'];
        $lastAdded = array_last($this->addedDates ?? []);

        if (!$end instanceof DateTimeImmutable || null === $lastAdded) {
            return $end;
        }

        return max($end, $this->occurrenceEnd($this->local(new DateTimeImmutable($lastAdded, new DateTimeZone(self::UTC)))));
    }

    private function computedSeriesEnd(): ?DateTimeImmutable
    {
        if ($this->rule instanceof RecurrenceRule && null === $this->rule->count && !$this->rule->until instanceof DateTimeImmutable) {
            return null;
        }

        return $this->occurrenceEnd($this->lastStart());
    }

    /**
     * When the occurrence that starts at a moment ends, in UTC, the day after its last for whole days.
     */
    private function occurrenceEnd(DateTimeImmutable $start): DateTimeImmutable
    {
        $end = $this->endOf($start, $this->lengthInDays(), $this->endTime());

        return ($this->allDay ? $end->modify('+1 day') : $end)->setTimezone(new DateTimeZone(self::UTC));
    }

    /**
     * Keeps the dates added after a start, in UTC: a date the event's new start passed is no longer an added occurrence.
     */
    private function keepAddedDatesAfter(string $start): void
    {
        $kept = array_values(array_filter($this->addedDates ?? [], static fn (string $date): bool => $date > $start));
        $this->addedDates = [] === $kept ? null : $kept;
    }

    private function local(DateTimeImmutable $utc): DateTimeImmutable
    {
        return $utc->setTimezone($this->timezone());
    }

    private function instant(DateTimeInterface $moment): string
    {
        return DateTimeImmutable::createFromInterface($moment)->setTimezone(new DateTimeZone(self::UTC))->format('Y-m-d\TH:i:s');
    }

    private static function zone(string $timezone): DateTimeZone
    {
        try {
            return new DateTimeZone($timezone);
        } catch (Exception) {
            throw InvalidEvent::because(\sprintf('"%s" is not a time zone.', mb_substr($timezone, 0, 100)));
        }
    }

    private static function checkedTitle(string $title): string
    {
        return self::text($title, 200, 'title') ?? throw InvalidEvent::because('An event has a title.');
    }

    private static function text(?string $text, int $length, string $what): ?string
    {
        $text = trim($text ?? '');

        if (mb_strlen($text) > $length) {
            throw InvalidEvent::because(\sprintf('The %s of an event is %d characters or fewer.', $what, $length));
        }

        return '' === $text ? null : $text;
    }

    /**
     * @return class-string<EventTranslation>
     */
    #[Override]
    public static function translationClass(): string
    {
        return EventTranslation::class;
    }

    /**
     * @return non-empty-list<string>
     */
    #[Override]
    public static function translatedFields(): array
    {
        return ['title', 'description'];
    }

    #[Override]
    public function newTranslation(Locale $locale): EventTranslation
    {
        return new EventTranslation($this, $locale);
    }
}
