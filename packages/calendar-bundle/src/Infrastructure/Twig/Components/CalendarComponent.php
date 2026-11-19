<?php

declare(strict_types=1);

namespace Phil\CalendarBundle\Infrastructure\Twig\Components;

use DateTimeImmutable;
use Exception;
use Phil\CalendarBundle\Application\ExpansionBudget;
use Phil\CalendarBundle\Application\View\Agenda;
use Phil\CalendarBundle\Application\View\CalendarView;
use Phil\CalendarBundle\Application\View\CalendarViews;
use Phil\CalendarBundle\Application\View\MonthGrid;
use Phil\CalendarBundle\Application\View\Period;
use Phil\CalendarBundle\Application\View\TimeGrid;
use Phil\CalendarBundle\Domain\ValueObject\Member;
use Psr\Clock\ClockInterface;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveArg;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;

/**
 * The calendar on a page, `<twig:PhilCalendar:Calendar />`: a period in one of its views, moved through and switched
 * without reloading the page. Its view, date and tags are in the address, so a link, a reload or a browser without
 * JavaScript shows the same period: the toolbar's buttons are links the component follows when it can.
 *
 * Given a `member`, `kind:reference`, it shows that member's own calendar: what they attend, host and follow, less what
 * they hide. The page sets it from who is signed in; it is not writable, and Live Component refuses a request that
 * changes it. When the request's `ExpansionBudget` stops the period short, `isCut()` says so for the template.
 */
#[AsLiveComponent(name: 'PhilCalendar:Calendar', template: '@PhilCalendar/components/Calendar.html.twig', exposePublicProps: false)]
final class CalendarComponent
{
    use DefaultActionTrait;

    /**
     * How many years from today, before or after, the date can be: a date from the address is anyone's, and a period
     * far from today is no calendar's.
     */
    public const int YEARS = 100;

    #[LiveProp(writable: true, url: true)]
    public string $view = 'month';

    /** The date the period holds, `Y-m-d`; today when empty. */
    #[LiveProp(writable: true, url: true)]
    public string $date = '';

    /** @var list<string> only the items with one of these tags */
    #[LiveProp(writable: true, url: true)]
    public array $tags = [];

    /** @var list<string> the views offered, in their order */
    #[LiveProp]
    public array $views = ['month', 'week', 'day', 'list', 'posters'];

    /** `hosts`, `venues` or `tags`: the week and the day shared between them. */
    #[LiveProp]
    public ?string $shared = null;

    #[LiveProp]
    public int $firstHour = 0;

    #[LiveProp]
    public int $lastHour = 24;

    #[LiveProp]
    public int $lanes = 3;

    /** The member whose own calendar it shows, `kind:reference`; the whole calendar when null. */
    #[LiveProp]
    public ?string $member = null;

    public function __construct(
        private readonly CalendarViews $calendar,
        private readonly ClockInterface $clock,
        private readonly ?ExpansionBudget $budget = null,
    ) {
    }

    #[LiveAction]
    public function previous(): void
    {
        $this->date = $this->period()->previous()->date->format('Y-m-d');
    }

    #[LiveAction]
    public function next(): void
    {
        $this->date = $this->period()->next()->date->format('Y-m-d');
    }

    #[LiveAction]
    public function today(): void
    {
        $this->date = '';
    }

    #[LiveAction]
    public function show(#[LiveArg] string $view, #[LiveArg] ?string $date = null): void
    {
        $this->view = $this->viewOf($view)->value;
        $this->date = $date ?? $this->date;
    }

    public function period(): Period
    {
        return $this->calendar->period($this->viewOf($this->view), $this->day());
    }

    public function month(): MonthGrid
    {
        return $this->calendar->month($this->period(), $this->tagFilter(), $this->lanes, $this->memberOf());
    }

    public function time(): TimeGrid
    {
        return $this->calendar->time($this->period(), $this->tagFilter(), $this->shared, $this->firstHour, $this->lastHour, $this->memberOf());
    }

    public function agenda(): Agenda
    {
        return $this->calendar->agenda($this->period(), $this->tagFilter(), $this->memberOf());
    }

    /**
     * Whether some events of the period were left out, the request's budget of occurrences spent: read after the view.
     */
    public function isCut(): bool
    {
        return $this->budget?->isExhausted() ?? false;
    }

    /**
     * Today, in the calendar's time zone, for the grid to mark it.
     */
    public function todayDate(): DateTimeImmutable
    {
        return $this->calendar->period(CalendarView::Day, $this->now())->date;
    }

    private function viewOf(string $view): CalendarView
    {
        $known = CalendarView::tryFrom($view);

        return null !== $known && \in_array($known->value, $this->views, true) ? $known : CalendarView::from($this->views[0] ?? 'month');
    }

    /**
     * The date asked for, or today when it is empty, cannot be read, or is more than `YEARS` years from today.
     */
    private function day(): DateTimeImmutable
    {
        $now = $this->now();

        if (1 === preg_match('/^\d{4}-\d{2}-\d{2}$/D', $this->date)) {
            try {
                $day = new DateTimeImmutable($this->date, $this->calendar->zone());
            } catch (Exception) {
                return $now;
            }

            $years = abs((int) $day->format('Y') - (int) $now->format('Y'));

            return $years <= self::YEARS ? $day : $now;
        }

        return $now;
    }

    private function now(): DateTimeImmutable
    {
        return $this->clock->now();
    }

    /**
     * The member whose calendar it shows: a value that names no member is the page's mistake, an `InvalidEvent`.
     */
    private function memberOf(): ?Member
    {
        return null === $this->member ? null : Member::fromString($this->member);
    }

    /**
     * @return list<string>|null
     */
    private function tagFilter(): ?array
    {
        return [] === $this->tags ? null : $this->tags;
    }
}
