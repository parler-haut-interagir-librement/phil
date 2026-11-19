<?php

declare(strict_types=1);

namespace Phil\CalendarBundle\Tests\Unit\Infrastructure;

use DateTimeImmutable;
use DateTimeZone;
use Phil\CalendarBundle\Application\Attendees;
use Phil\CalendarBundle\Application\Calendar;
use Phil\CalendarBundle\Application\CalendarChoiceDraft;
use Phil\CalendarBundle\Application\CalendarChoices;
use Phil\CalendarBundle\Application\EventDraft;
use Phil\CalendarBundle\Application\ExpansionBudget;
use Phil\CalendarBundle\Application\ICalendar;
use Phil\CalendarBundle\Application\PersonalCalendar;
use Phil\CalendarBundle\Application\PersonalFeeds;
use Phil\CalendarBundle\Application\View\CalendarViews;
use Phil\CalendarBundle\Application\View\EventItems;
use Phil\CalendarBundle\Application\View\Item;
use Phil\CalendarBundle\Application\View\Items;
use Phil\CalendarBundle\Application\View\ItemSource;
use Phil\CalendarBundle\Domain\Choice\ChoiceSource;
use Phil\CalendarBundle\Domain\Exception\InvalidEvent;
use Phil\CalendarBundle\Domain\Model\Event;
use Phil\CalendarBundle\Domain\ValueObject\Member;
use Phil\CalendarBundle\Infrastructure\Form\CalendarChoiceType;
use Phil\CalendarBundle\Infrastructure\Http\PersonalFeedController;
use Phil\CalendarBundle\Infrastructure\Privacy\CalendarPrivacy;
use Phil\CalendarBundle\Infrastructure\Twig\Components\CalendarComponent;
use Phil\CalendarBundle\Tests\Unit\Fixtures\InMemoryAttendances;
use Phil\CalendarBundle\Tests\Unit\Fixtures\InMemoryCalendarChoices;
use Phil\CalendarBundle\Tests\Unit\Fixtures\InMemoryEvents;
use Phil\CalendarBundle\Tests\Unit\Fixtures\InMemoryFeedTokens;
use Phil\CalendarBundle\Tests\Unit\Fixtures\InMemoryVenues;
use Phil\PrivacyBundle\Domain\Retention\ErasureEffect;
use Phil\PrivacyBundle\Domain\Subject\DataSubject;
use Phil\Recurrence\RecurrenceRule;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Form\ChoiceList\View\ChoiceView;
use Symfony\Component\Form\Forms;
use Symfony\Component\HttpFoundation\Request;

#[CoversClass(ICalendar::class)]
#[CoversClass(PersonalFeedController::class)]
#[CoversClass(CalendarPrivacy::class)]
#[CoversClass(CalendarChoiceType::class)]
#[CoversClass(CalendarComponent::class)]
#[CoversClass(EventItems::class)]
#[CoversClass(Items::class)]
#[CoversClass(CalendarViews::class)]
final class PersonalFeedTest extends TestCase
{
    private InMemoryEvents $events;

    private InMemoryAttendances $attendances;

    private Calendar $calendar;

    private CalendarChoices $choices;

    private PersonalFeeds $feeds;

    private PersonalCalendar $personal;

    private MockClock $clock;

    private Member $ana;

    protected function setUp(): void
    {
        $this->clock = new MockClock('2026-11-03 12:00:00 UTC');
        $this->events = new InMemoryEvents();
        $this->attendances = new InMemoryAttendances();
        $this->calendar = new Calendar($this->events);
        $this->choices = new CalendarChoices(new InMemoryCalendarChoices(), $this->clock);
        $this->feeds = new PersonalFeeds(new InMemoryFeedTokens(), $this->clock);
        $this->personal = new PersonalCalendar($this->calendar, $this->attendances, $this->events, $this->choices);
        $this->ana = Member::of('member', 'ana');
    }

    public function testOccurrencesAreAnICalendarFileOfTheirOwn(): void
    {
        $weekly = $this->event('Club', '2026-11-10 19:00', '2026-11-10 21:00', 'FREQ=WEEKLY;COUNT=3');
        $weekly->linkDate($this->at('2026-11-17 19:00'), 'https://example.com/club/17');
        $weekly->describe(null, null, 'https://example.com/club');

        $fair = $this->event('Foire', '2026-11-14 00:00', '2026-11-15 00:00', allDay: true);
        $occurrences = [...$weekly->occurrences($this->at('2026-11-15 00:00'), $this->at('2026-12-01 00:00')), ...$fair->occurrences($this->at('2026-11-01 00:00'), $this->at('2026-12-01 00:00'))];

        $file = ICalendar::ofOccurrences($occurrences, new DateTimeImmutable('2026-11-03 12:00 UTC'), name: 'Mon calendrier');

        self::assertStringStartsWith("BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//PHIL//phil/calendar-bundle//EN\r\nCALSCALE:GREGORIAN\r\nX-WR-CALNAME:Mon calendrier\r\n", $file);
        self::assertStringContainsString("UID:{$weekly->id}@20261118T000000Z\r\nDTSTAMP:20261103T120000Z\r\nDTSTART;TZID=America/Toronto:20261117T190000\r\nDTEND;TZID=America/Toronto:20261117T210000\r\n", $file);
        self::assertStringContainsString("URL:https://example.com/club/17\r\n", $file);
        self::assertStringContainsString("UID:{$weekly->id}@20261125T000000Z\r\n", $file);
        self::assertStringContainsString("URL:https://example.com/club\r\n", $file, "the event's page for a date without one");
        self::assertStringContainsString("DTSTART;VALUE=DATE:20261114\r\nDTEND;VALUE=DATE:20261116\r\n", $file);
        self::assertStringNotContainsString('RRULE', $file, 'each date stands alone');
        self::assertSame(3, substr_count($file, 'BEGIN:VEVENT'));
        self::assertStringEndsWith("END:VCALENDAR\r\n", $file);
        self::assertStringNotContainsString('X-WR-CALNAME', ICalendar::ofOccurrences([], new DateTimeImmutable()));
    }

    public function testTheFeedAtTheAddressOfASecret(): void
    {
        $club = $this->event('Club', '2026-11-10 19:00', '2026-11-10 21:00', 'FREQ=WEEKLY;COUNT=3');
        $other = $this->event('Autre', '2026-11-11 19:00', '2026-11-11 21:00');
        new Attendees($this->attendances, $this->clock)->attend($club, $this->ana);
        $secret = $this->feeds->issue($this->ana);
        $controller = new PersonalFeedController($this->feeds, $this->personal, $this->clock, 'Le Club');

        $response = $controller->ics(new Request(), $secret);
        self::assertSame(200, $response->getStatusCode());
        self::assertSame('text/calendar; charset=utf-8', $response->headers->get('Content-Type'));
        self::assertSame('inline; filename=calendar.ics', $response->headers->get('Content-Disposition'));
        self::assertSame('no-cache, private', $response->headers->get('Cache-Control'));
        self::assertSame('noindex, nofollow', $response->headers->get('X-Robots-Tag'));
        self::assertSame('no-referrer', $response->headers->get('Referrer-Policy'));
        $body = (string) $response->getContent();
        self::assertSame(3, substr_count($body, 'SUMMARY:Club'));
        self::assertStringNotContainsString('Autre', $body);
        self::assertStringContainsString('X-WR-CALNAME:Le Club', $body);
        $etag = (string) $response->getEtag();
        self::assertNotSame('', $etag);

        $again = $controller->ics(new Request(server: ['HTTP_IF_NONE_MATCH' => $etag]), $secret);
        self::assertSame(304, $again->getStatusCode());
        self::assertSame('no-cache, private', $again->headers->get('Cache-Control'));

        // Another occurrence in the member's calendar changes the tag.
        $this->choices->follow($this->ana, ChoiceSource::Event, $other->id);
        self::assertSame(200, $controller->ics(new Request(server: ['HTTP_IF_NONE_MATCH' => $etag]), $secret)->getStatusCode());
        self::assertNotSame($etag, (string) new PersonalFeedController($this->feeds, $this->personal, $this->clock)->ics(new Request(), $secret)->getEtag(), 'the name is in the tag');

        $named = (string) $controller->ics(new Request(), $secret)->getEtag();
        self::assertNotSame($named, (string) new PersonalFeedController($this->feeds, $this->personal, $this->clock)->ics(new Request(), $secret)->getEtag(), 'the name is in the tag');
        self::assertNotSame($named, (string) new PersonalFeedController($this->feeds, $this->personal, $this->clock, 'Autre')->ics(new Request(), $secret)->getEtag());

        // Revoked, unknown, malformed: not found, and private still.
        $this->feeds->revoke($this->ana);
        foreach ([$secret, str_repeat('a', 43), 'nope'] as $wrong) {
            $missing = $controller->ics(new Request(), $wrong);
            self::assertSame([404, '', 'no-cache, private', 'noindex, nofollow'], [$missing->getStatusCode(), $missing->getContent(), $missing->headers->get('Cache-Control'), $missing->headers->get('X-Robots-Tag')]);
        }
    }

    public function testThePrivacyOfAMember(): void
    {
        $club = $this->event('Club', '2026-11-10 19:00', '2026-11-10 21:00');
        $proposal = $this->calendar->schedule($this->draft('Proposé', '2026-11-12 19:00', '2026-11-12 21:00'), $this->ana);
        new Attendees($this->attendances, $this->clock)->attend($club, $this->ana);
        new Attendees($this->attendances, $this->clock)->attend($club, Member::of('member', 'ben'));
        $this->choices->follow($this->ana, ChoiceSource::Tag, 'jeux');
        $this->feeds->issue($this->ana);
        $privacy = new CalendarPrivacy($this->choices, $this->feeds, $this->attendances, $this->events);
        $ana = DataSubject::fromString('ana');

        self::assertSame('calendar', $privacy->module()->value);
        self::assertSame([
            'choices' => [['source' => 'tag', 'value' => 'jeux', 'hidden' => false, 'chosen_at' => '2026-11-03T12:00:00+00:00']],
            'feed_issued_at' => '2026-11-03T12:00:00+00:00',
            'attendances' => [$club->id => ['']],
            'proposals' => [['event' => $proposal->id, 'title' => 'Proposé', 'state' => 'proposed']],
        ], $privacy->export($ana));
        self::assertSame([], $privacy->export(DataSubject::fromString('nobody')));
        self::assertSame([], $privacy->export(DataSubject::fromString('a/b')), 'no member has such a reference');
        self::assertSame([], new CalendarPrivacy($this->choices, $this->feeds, $this->attendances, $this->events, 'guest')->export($ana), 'another kind of member');

        foreach ([1, 2] as $time) {
            $report = $privacy->erase($ana);
            self::assertSame('calendar', $report->module->value);
            self::assertSame([
                'phil_calendar_choice' => ErasureEffect::Deleted,
                'phil_calendar_feed_token' => ErasureEffect::Deleted,
                'phil_calendar_attendance' => ErasureEffect::Deleted,
                'phil_calendar_event.proposed_by' => ErasureEffect::Redacted,
            ], $report->effects, 'the same report, time '.$time);
            self::assertSame([], $privacy->export($ana));
        }

        self::assertNull($proposal->proposedBy());
        self::assertSame(Event::PROPOSED, $proposal->getProposalState(), 'the proposal stays');
        self::assertSame(1, $this->attendances->count($club, ''), "the other member's attendance stays");
        self::assertSame(ErasureEffect::Deleted, $privacy->erase(DataSubject::fromString('a/b'))->effects['phil_calendar_choice']);
    }

    public function testTheFormOfAChoice(): void
    {
        $form = Forms::createFormFactoryBuilder()->getFormFactory()->create(CalendarChoiceType::class);
        $form->submit(['source' => 'tag', 'value' => 'jeux', 'hidden' => '1']);

        $following = Forms::createFormFactoryBuilder()->getFormFactory()->create(CalendarChoiceType::class);
        $following->submit(['source' => 'tag', 'value' => 'jeux', 'hidden' => '0']);
        self::assertInstanceOf(CalendarChoiceDraft::class, $following->getData());
        self::assertFalse($following->getData()->hidden);

        $draft = $form->getData();
        self::assertInstanceOf(CalendarChoiceDraft::class, $draft);
        self::assertSame(['tag', 'jeux', true], [$draft->source, $draft->value, $draft->hidden]);

        $view = Forms::createFormFactoryBuilder()->getFormFactory()->create(CalendarChoiceType::class)->createView();
        self::assertSame(['calendar.choice.source.label', 'calendar.choice.value', 'calendar.choice.mode'], [$view['source']->vars['label'], $view['value']->vars['label'], $view['hidden']->vars['label']]);
        $choices = static function (mixed $choices): array {
            self::assertIsArray($choices);

            return array_values(array_map(static function (mixed $choice): array {
                self::assertInstanceOf(ChoiceView::class, $choice);

                return [$choice->value, $choice->label];
            }, $choices));
        };
        self::assertSame([['host', 'calendar.choice.source.host'], ['tag', 'calendar.choice.source.tag'], ['venue', 'calendar.choice.source.venue'], ['event', 'calendar.choice.source.event']], $choices($view['source']->vars['choices']));
        self::assertSame([['0', 'calendar.choice.follow'], ['1', 'calendar.choice.hide']], $choices($view['hidden']->vars['choices']));
        self::assertTrue($view['hidden']->vars['expanded']);
        self::assertSame(['maxlength' => 129], $view['value']->vars['attr']);
        self::assertSame('phil_calendar', $view->vars['translation_domain']);
    }

    public function testTheComponentShowsAMembersOwnCalendar(): void
    {
        $club = $this->event('Club', '2026-11-10 19:00', '2026-11-10 21:00');
        $this->event('Autre', '2026-11-11 19:00', '2026-11-11 21:00');
        new Attendees($this->attendances, $this->clock)->attend($club, $this->ana);
        $day = new readonly class implements ItemSource {
            public function items(DateTimeImmutable $from, DateTimeImmutable $until): array
            {
                return [new Item('day', 'Jour', $from, $from, true)];
            }
        };
        $budget = new ExpansionBudget(3, 10);
        $calendar = new Calendar($this->events, null, $budget);
        $items = new Items([new EventItems($calendar, $this->events, null, new PersonalCalendar($calendar, $this->attendances, $this->events, $this->choices)), $day]);
        $component = new CalendarComponent(new CalendarViews($items, new InMemoryVenues(), 'America/Toronto'), $this->clock, $budget);
        $titles = static fn (array $items): array => array_map(static fn (Item $item): string => $item->title, $items);

        $component->view = 'list';
        $component->member = 'member:ana';
        self::assertSame(['Jour', 'Club'], $titles($component->agenda()->items()), 'the other sources stay');
        self::assertFalse($component->isCut());
        $component->view = 'month';
        $budget->reset();
        self::assertCount(2, array_merge(...array_map(static fn (object $week): array => $week->bars, $component->month()->weeks)));
        $component->view = 'week';
        $component->date = '2026-11-10';

        $budget->reset();
        self::assertSame(['Club'], $titles(array_merge(...array_map(static fn (object $column): array => array_map(static fn (object $block): Item => $block->item, $column->blocks), $component->time()->columns))));

        $component->member = null;
        $component->view = 'list';
        $component->date = '';

        $budget->reset();
        self::assertSame(['Jour', 'Club', 'Autre'], $titles($component->agenda()->items()));
        self::assertFalse($component->isCut());

        $this->event('Encore', '2026-11-12 19:00', '2026-11-12 21:00');
        $this->event('Plus', '2026-11-13 19:00', '2026-11-13 21:00');
        $budget->reset();
        self::assertSame(['Jour', 'Club', 'Autre', 'Encore'], $titles($component->agenda()->items()), 'three occurrences in the budget: the calendar is cut');
        self::assertTrue($component->isCut());
        self::assertFalse(new CalendarComponent(new CalendarViews($items, new InMemoryVenues()), $this->clock)->isCut());

        // Without a member's calendar, a member's view has no event.
        $alone = new CalendarComponent(new CalendarViews(new Items([new EventItems($this->calendar, $this->events)]), new InMemoryVenues(), 'America/Toronto'), $this->clock);
        $alone->view = 'list';
        $alone->member = 'member:ana';
        self::assertSame([], $alone->agenda()->items());

        $component->member = 'nobody';
        $this->expectException(InvalidEvent::class);
        $component->agenda();
    }

    private function event(string $title, string $start, string $end, ?string $rule = null, bool $allDay = false): Event
    {
        $draft = $this->draft($title, $start, $end, $rule);
        $draft->allDay = $allDay;

        return $this->calendar->schedule($draft);
    }

    private function draft(string $title, string $start, string $end, ?string $rule = null): EventDraft
    {
        $draft = new EventDraft();
        $draft->title = $title;
        $draft->start = new DateTimeImmutable($start);
        $draft->end = new DateTimeImmutable($end);
        $draft->timezone = 'America/Toronto';
        $draft->rule = null === $rule ? null : RecurrenceRule::parse($rule);

        return $draft;
    }

    private function at(string $time): DateTimeImmutable
    {
        return new DateTimeImmutable($time, new DateTimeZone('America/Toronto'));
    }
}
