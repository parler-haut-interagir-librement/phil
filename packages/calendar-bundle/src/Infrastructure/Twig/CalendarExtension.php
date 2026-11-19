<?php

declare(strict_types=1);

namespace Phil\CalendarBundle\Infrastructure\Twig;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use IntlDateFormatter;
use Locale;
use Phil\CalendarBundle\Application\Attendees;
use Phil\CalendarBundle\Application\Calendar;
use Phil\CalendarBundle\Application\CalendarChoices;
use Phil\CalendarBundle\Application\EventSchema;
use Phil\CalendarBundle\Application\EventTexts;
use Phil\CalendarBundle\Application\EventTextSet;
use Phil\CalendarBundle\Application\MemberChoices;
use Phil\CalendarBundle\Application\PersonalCalendar;
use Phil\CalendarBundle\Application\View\CalendarView;
use Phil\CalendarBundle\Application\View\DaySpan;
use Phil\CalendarBundle\Application\View\Period;
use Phil\CalendarBundle\Domain\Model\Event;
use Phil\CalendarBundle\Domain\ValueObject\Member;
use Phil\CalendarBundle\Domain\ValueObject\Occurrence;
use Phil\StructuredData\Contract\NodeCollector;
use Phil\StructuredData\Node;
use Twig\Attribute\AsTwigFilter;
use Twig\Attribute\AsTwigFunction;

/**
 * The calendar in templates: the occurrences of a period, and when one takes place, written by ICU in the request's
 * language unless one is given — *mardi 10 novembre 2026, 19 h 00 – 21 h 00*. A formatter is built once for each
 * language, style, time zone and pattern, then kept: a month of a calendar formats hundreds of moments with a few.
 * In a worker that lives across requests, 64 formatters are kept at most: past them, they are built again, so that
 * languages and time zones taken from requests cannot grow the memory without end.
 */
final class CalendarExtension
{
    /** The formatters kept at most. */
    private const int KEPT = 64;

    /** @var array<string, array<int, array<int, array<string, array<string, IntlDateFormatter>>>>> by language, styles, time zone and pattern */
    private array $formatters = [];

    /** How many formatters are kept. */
    private int $kept = 0;

    public function __construct(
        private readonly Calendar $calendar,
        private readonly ?NodeCollector $graph = null,
        private readonly ?EventTexts $texts = null,
        private readonly ?Attendees $attendees = null,
        private readonly ?PersonalCalendar $personal = null,
        private readonly KitComponents $kit = new KitComponents(),
        private readonly ?CalendarChoices $choices = null,
    ) {
    }

    /**
     * The name under which a recipe of the PHIL UX kit is installed, for the templates of the bundle:
     * `{% component (phil_calendar_kit('Popover')) %}`.
     */
    #[AsTwigFunction(name: 'phil_calendar_kit')]
    public function kit(string $recipe): string
    {
        return $this->kit->name($recipe);
    }

    /**
     * What a member follows and hides, for `@PhilCalendar/calendar/_choices.html.twig`.
     */
    #[AsTwigFunction(name: 'calendar_choices')]
    public function choices(string $kind, string $reference): MemberChoices
    {
        return $this->choices?->of(Member::of($kind, $reference)) ?? new MemberChoices();
    }

    /**
     * A member's own calendar: the occurrences of a period they attend, host or follow, less what they hide, in order.
     *
     * @return list<Occurrence>
     */
    #[AsTwigFunction(name: 'calendar_personal')]
    public function personal(string $kind, string $reference, DateTimeInterface $from, DateTimeInterface $through): array
    {
        return $this->personal?->occurrences(Member::of($kind, $reference), $from, $through) ?? [];
    }

    /**
     * How many attend an occurrence, or an event as a whole.
     */
    #[AsTwigFunction(name: 'calendar_attendance')]
    public function attendance(Occurrence|Event $what): int
    {
        return $this->attendees?->count($what) ?? 0;
    }

    /**
     * The places left at an occurrence, or null for an event without a capacity.
     */
    #[AsTwigFunction(name: 'calendar_places_left')]
    public function placesLeft(Occurrence|Event $what): ?int
    {
        return $this->attendees?->placesLeft($what);
    }

    /**
     * Whether a member attends an occurrence — or every one of its event — or an event as a whole.
     */
    #[AsTwigFunction(name: 'calendar_attends')]
    public function attends(Occurrence|Event $what, string $kind, string $reference): bool
    {
        return $this->attendees?->isAttending($what, Member::of($kind, $reference)) ?? false;
    }

    /**
     * The texts of events — titles, descriptions, venues — in a locale, the request's by default: read once for a list,
     * then `texts.title(event)` for each.
     *
     * @param Event|iterable<Event> $events
     */
    #[AsTwigFunction(name: 'calendar_texts')]
    public function texts(Event|iterable $events, ?string $locale = null): EventTextSet
    {
        return $this->texts?->of($events instanceof Event ? [$events] : $events, $locale) ?? new EventTextSet();
    }

    /**
     * @return list<Occurrence>
     */
    #[AsTwigFunction(name: 'calendar_occurrences')]
    public function occurrences(DateTimeInterface $from, DateTimeInterface $through, ?string $hostKind = null, ?string $hostReference = null, ?string $tag = null): array
    {
        return $this->calendar->occurrences($from, $through, $hostKind, $hostReference, $tag);
    }

    /**
     * The days and times of an occurrence: one day with its times, several days, whole days without times.
     */
    #[AsTwigFilter(name: 'occurrence_when')]
    public function when(Occurrence $occurrence, ?string $locale = null): string
    {
        $locale ??= Locale::getDefault();
        $zone = $occurrence->start->getTimezone();
        $day = fn (DateTimeInterface $moment): string => $this->format($locale, IntlDateFormatter::FULL, IntlDateFormatter::NONE, $zone, null, $moment);
        $time = fn (DateTimeInterface $moment): string => $this->format($locale, IntlDateFormatter::NONE, IntlDateFormatter::SHORT, $zone, null, $moment);
        $sameDay = $occurrence->start->format('Y-m-d') === $occurrence->end->format('Y-m-d');

        return match (true) {
            $occurrence->event->isAllDay() && $sameDay => $day($occurrence->start),
            $occurrence->event->isAllDay() => $day($occurrence->start).' – '.$day($occurrence->end),
            $sameDay => $day($occurrence->start).', '.$time($occurrence->start).' – '.$time($occurrence->end),
            default => $day($occurrence->start).', '.$time($occurrence->start).' – '.$day($occurrence->end).', '.$time($occurrence->end),
        };
    }

    /**
     * The days a group of the list covers, in words: *1er et 2 septembre*.
     */
    #[AsTwigFunction(name: 'calendar_days')]
    public function days(DateTimeImmutable $first, DateTimeImmutable $last, ?int $year = null, ?string $locale = null): string
    {
        return DaySpan::label($first, $last, $locale ?? Locale::getDefault(), $year);
    }

    /**
     * What a period is called: *septembre 2026*, the days of a week, a day.
     */
    #[AsTwigFilter(name: 'calendar_title')]
    public function title(Period $period, ?string $locale = null): string
    {
        $locale ??= Locale::getDefault();

        return match ($period->view) {
            CalendarView::Week => DaySpan::label($period->from, $period->until->modify('-1 day'), $locale),
            CalendarView::Day => DaySpan::label($period->date, $period->date, $locale),
            default => $this->pattern($period->date, 'LLLL y', $locale),
        };
    }

    /**
     * A moment written with an ICU pattern in the request's language: `HH:mm`, `EEE`, `d`.
     */
    #[AsTwigFilter(name: 'calendar_format')]
    public function pattern(DateTimeInterface $moment, string $pattern, ?string $locale = null): string
    {
        return $this->format($locale ?? Locale::getDefault(), IntlDateFormatter::NONE, IntlDateFormatter::NONE, $moment->getTimezone(), $pattern, $moment);
    }

    /**
     * A time as the language writes it short: *19 h 00*, *7:00 PM*.
     */
    #[AsTwigFilter(name: 'calendar_time')]
    public function time(DateTimeInterface $moment, ?string $locale = null): string
    {
        return $this->format($locale ?? Locale::getDefault(), IntlDateFormatter::NONE, IntlDateFormatter::SHORT, $moment->getTimezone(), null, $moment);
    }

    /**
     * An hour of the day as the language writes it short: *9 h 00*, *9:00 AM*.
     */
    #[AsTwigFilter(name: 'calendar_hour')]
    public function hour(int $hour, ?string $locale = null): string
    {
        return $this->time(new DateTimeImmutable('2000-01-01', new DateTimeZone('UTC'))->setTime($hour, 0), $locale);
    }

    /**
     * The address of a view and a date, as a query on the page: what the calendar's links lead to without JavaScript.
     *
     * @param list<string> $tags
     */
    #[AsTwigFunction(name: 'calendar_link')]
    public function link(string $view, string $date, array $tags = []): string
    {
        return '?'.http_build_query(array_filter(['view' => $view, 'date' => $date, 'tags' => $tags], static fn (string|array $value): bool => [] !== $value && '' !== $value));
    }

    private function format(string $locale, int $dateType, int $timeType, DateTimeZone $zone, ?string $pattern, DateTimeInterface $moment): string
    {
        $formatter = $this->formatters[$locale][$dateType][$timeType][$zone->getName()][$pattern ?? ''] ?? $this->keep($locale, $dateType, $timeType, $zone, $pattern);
        $text = $formatter->format($moment);

        return false === $text ? '' : $text;
    }

    /**
     * The event as schema.org JSON-LD data, an `Event`.
     *
     * @return array<string, mixed>
     */
    #[AsTwigFunction(name: 'calendar_event_schema')]
    public function schema(Event $event, ?string $locale = null): array
    {
        return EventSchema::of($event, $this->texts($event, $locale));
    }

    /**
     * The event's node added to the graph of the page, which prints it with the others, and nothing printed here;
     * without a graph, `<script type="application/ld+json">…</script>`, its JSON unable to close the element.
     */
    #[AsTwigFunction(name: 'calendar_event_json_ld', isSafe: ['html'])]
    public function jsonLd(Event $event, ?string $locale = null): string
    {
        $schema = $this->schema($event, $locale);

        if ($this->graph instanceof NodeCollector) {
            $this->graph->add(Node::fromArray($schema));

            return '';
        }

        return '<script type="application/ld+json">'.json_encode($schema, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE | \JSON_HEX_TAG | \JSON_HEX_AMP).'</script>';
    }

    private function keep(string $locale, int $dateType, int $timeType, DateTimeZone $zone, ?string $pattern): IntlDateFormatter
    {
        if ($this->kept >= self::KEPT) {
            $this->formatters = [];
            $this->kept = 0;
        }

        ++$this->kept;

        return $this->formatters[$locale][$dateType][$timeType][$zone->getName()][$pattern ?? ''] = new IntlDateFormatter($locale, $dateType, $timeType, $zone, null, $pattern);
    }
}
