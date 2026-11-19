<?php

declare(strict_types=1);

namespace Phil\CalendarBundle\Tests\Functional;

use DateTimeImmutable;
use DateTimeZone;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Override;
use Phil\CalendarBundle\Application\Attendees;
use Phil\CalendarBundle\Application\Calendar;
use Phil\CalendarBundle\Application\EventDraft;
use Phil\CalendarBundle\Application\EventTexts;
use Phil\CalendarBundle\Application\PersonalCalendar;
use Phil\CalendarBundle\Application\PrintableCalendar;
use Phil\CalendarBundle\Application\Proposals;
use Phil\CalendarBundle\Application\View\AgendaGroup;
use Phil\CalendarBundle\Application\View\CalendarView;
use Phil\CalendarBundle\Application\View\CalendarViews;
use Phil\CalendarBundle\Application\View\Item;
use Phil\CalendarBundle\Domain\Exception\AttendanceRefused;
use Phil\CalendarBundle\Domain\Model\Event;
use Phil\CalendarBundle\Domain\Model\Venue;
use Phil\CalendarBundle\Domain\Repository\AttendanceRepository;
use Phil\CalendarBundle\Domain\Repository\EventRepository;
use Phil\CalendarBundle\Domain\ValueObject\Member;
use Phil\CalendarBundle\Domain\ValueObject\Occurrence;
use Phil\CalendarBundle\Infrastructure\Doctrine\DoctrineAttendanceRepository;
use Phil\CalendarBundle\PhilCalendarBundle;
use Phil\CalendarBundle\Tests\Functional\App\TestKernel;
use Phil\Recurrence\RecurrenceRule;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Workflow\Event\GuardEvent;
use Symfony\Component\Workflow\Exception\NotEnabledTransitionException;
use Twig\Environment;

/**
 * The calendar's translations, attendees, members' calendars, proposals and printed months, on Doctrine with SQLite.
 */
#[CoversClass(PhilCalendarBundle::class)]
#[CoversClass(DoctrineAttendanceRepository::class)]
final class CalendarFeaturesTest extends KernelTestCase
{
    private const string ZONE = 'America/Toronto';

    #[Override]
    protected static function getKernelClass(): string
    {
        return TestKernel::class;
    }

    #[Override]
    protected function setUp(): void
    {
        if (!ContainerBuilder::willBeAvailable('symfony/event-dispatcher', EventDispatcher::class, ['symfony/dependency-injection'])) {
            self::markTestSkipped('The event dispatcher is installed for development only: no kernel can boot.');
        }

        self::bootKernel();
        $entityManager = $this->entityManager();
        new SchemaTool($entityManager)->createSchema($entityManager->getMetadataFactory()->getAllMetadata());
    }

    /**
     * An event and its venue translated in English: served in English to a reader of English, in its own text to a
     * reader of French whose chain has no translation, in views, schema.org data and Twig alike.
     */
    public function testTheTranslations(): void
    {
        $venue = new Venue('Grande salle');
        $this->entityManager()->persist($venue);
        $draft = $this->draft('Soirée jeux', '2026-11-10 19:00', '2026-11-10 22:00');
        $draft->description = 'Apportez vos jeux.';
        $draft->venue = $venue;

        $event = $this->service(Calendar::class)->schedule($draft);

        $texts = $this->service(EventTexts::class);
        $texts->translate($event, 'en')->write('Game night', 'Bring your games.');
        $texts->translateVenue($venue, 'en')->rename('Main hall');
        $this->entityManager()->flush();
        $this->entityManager()->clear();

        $event = $this->service(EventRepository::class)->get($event->id) ?? self::fail('Saved.');
        $english = $texts->of([$event], 'en');
        self::assertSame(['Game night', 'Bring your games.', 'Main hall', true], [$english->title($event), $english->description($event), $english->place($event), $english->isTranslated($event)]);
        $french = $texts->of([$event], 'fr_CA');
        self::assertSame(['Soirée jeux', 'Apportez vos jeux.', 'Grande salle', false], [$french->title($event), $french->description($event), $french->place($event), $french->isTranslated($event)]);
        self::assertSame('Soirée jeux', $texts->of([$event], 'de')->title($event), 'a locale not served shows the event in its own text');
        self::assertSame(['en'], $texts->locales($event));
        self::assertSame($texts->translate($event, 'en')->title(), 'Game night', 'the stored translation is given back to be written');

        $twig = $this->twig();
        self::assertSame('Game night|Main hall|Soirée jeux', $twig->createTemplate("{% set en = calendar_texts(event, 'en') %}{{ en.title(event) }}|{{ calendar_event_schema(event, 'en').location.name }}|{{ calendar_texts([event]).title(event) }}")->render(['event' => $event]));

        // The views speak the request's locale, French here: the event in its own text.
        $views = $this->service(CalendarViews::class);
        $agenda = $views->agenda($views->period(CalendarView::List, new DateTimeImmutable('2026-11-10', new DateTimeZone(self::ZONE))));
        self::assertSame('Soirée jeux', $agenda->groups[0]->items[0]->title ?? null);
    }

    /**
     * Members attend an occurrence or every one; the capacity counts both; the lists are read a page at a time.
     */
    public function testTheAttendees(): void
    {
        $draft = $this->draft('Atelier', '2026-11-10 19:00', '2026-11-10 21:00', 'FREQ=WEEKLY;COUNT=3');
        $draft->capacity = 2;

        $event = $this->service(Calendar::class)->schedule($draft);
        [$first, $second] = $event->occurrences(new DateTimeImmutable('2026-11-01 UTC'), new DateTimeImmutable('2026-12-01 UTC'));

        $attendees = $this->service(Attendees::class);
        $ana = Member::of('member', 'ana');
        $ben = Member::of('member', 'ben');
        $cleo = Member::of('member', 'cleo');
        $kept = $attendees->attend($event, $ana);
        self::assertSame($kept->id, $attendees->attend($event, $ana)->id, 'signing up again gives the attendance kept');
        $attendees->attend($first, $ben);
        self::assertSame([2, 1, 0, 1], [$attendees->count($first), $attendees->count($second), $attendees->placesLeft($first), $attendees->placesLeft($second)]);
        self::assertTrue($attendees->isAttending($second, $ana), 'every occurrence of the event');
        self::assertFalse($attendees->isAttending($second, $ben));

        try {
            $attendees->attend($first, $cleo);
            self::fail('Full.');
        } catch (AttendanceRefused $attendanceRefused) {
            self::assertSame(AttendanceRefused::FULL, $attendanceRefused->reason);
        }

        $attendees->attend($second, $cleo);
        $this->entityManager()->clear();
        $event = $this->service(EventRepository::class)->get($event->id) ?? self::fail('Saved.');
        $first = new Occurrence($event, $first->start, $first->end);

        self::assertSame(['member:ana', 'member:ben'], array_map(strval(...), $attendees->attendees($first)));
        self::assertSame(['member:ben'], array_map(strval(...), $attendees->attendees($first, 1, 1)));
        self::assertSame(['member:ana'], array_map(strval(...), $attendees->attendees($event)));
        $repository = $this->service(AttendanceRepository::class);
        self::assertSame([$event->id => ['2026-11-18T00:00:00']], $repository->attendedBy($cleo));
        self::assertSame([$event->id => ['']], $repository->attendedBy($ana));

        // A member's own calendar: what they attend, and what they host under the same name.
        $hosted = $this->service(Calendar::class)->schedule($this->draft('Club', '2026-11-11 18:00', '2026-11-11 19:00'));
        $this->service(Calendar::class)->host($hosted, 'member', 'ben');
        $personal = $this->service(PersonalCalendar::class);
        $from = new DateTimeImmutable('2026-11-01', new DateTimeZone(self::ZONE));
        $through = new DateTimeImmutable('2026-12-01', new DateTimeZone(self::ZONE));
        self::assertSame(['Atelier 11-10', 'Club 11-11'], $this->days($personal->occurrences($ben, $from, $through)));
        self::assertSame(['Atelier 11-10'], $this->days($personal->occurrences($ben, $from, $through, hosted: false)));
        self::assertSame(['Atelier 11-10', 'Atelier 11-17', 'Atelier 11-24'], $this->days($personal->occurrences($ana, $from, $through)));
        self::assertSame([], $personal->occurrences(Member::of('member', 'nobody'), $from, $through));

        self::assertTrue($attendees->withdraw($first, $ben));
        self::assertFalse($attendees->withdraw($first, $ben));
        self::assertSame(1, $attendees->count($first));

        $twig = $this->twig();
        self::assertSame('1|1|yes|no|Club', $twig->createTemplate("{{ calendar_attendance(first) }}|{{ calendar_places_left(first) }}|{{ calendar_attends(first, 'member', 'ana') ? 'yes' : 'no' }}|{{ calendar_attends(first, 'member', 'ben') ? 'yes' : 'no' }}|{% for o in calendar_personal('member', 'ben', from, through) %}{{ o.event.title }}{% endfor %}")->render(['first' => $first, 'from' => $from, 'through' => $through]));
    }

    /**
     * A member's proposal stays out of the calendar until it is approved; a guard of the application holds a
     * transition; a refused proposal can be approved later.
     */
    public function testTheProposals(): void
    {
        $proposals = $this->service(Proposals::class);
        $calendar = $this->service(Calendar::class);
        $proposal = $proposals->propose($this->draft('Murder party', '2026-11-14 20:00', '2026-11-14 23:00'), Member::of('member', 'ana'));
        $from = new DateTimeImmutable('2026-11-01 UTC');
        $through = new DateTimeImmutable('2026-12-01 UTC');

        self::assertSame([], $this->titles($calendar, $from, $through));
        self::assertSame([$proposal->id], array_map(static fn (Event $event): string => $event->id, $proposals->waiting()));
        self::assertSame('member:ana', (string) $proposal->proposedBy());

        // A guard of the application holds the approval.
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener('workflow.phil_calendar_proposal.guard.approve', static function (GuardEvent $guard): void {
            $guard->setBlocked(true);
        });
        self::assertFalse(new Proposals($calendar, $this->service(EventRepository::class), $dispatcher)->can($proposal, 'approve'));
        self::assertTrue($proposals->can($proposal, 'approve'));

        $proposals->refuse($proposal);
        self::assertSame([], $proposals->waiting());
        $proposals->approve($proposal);
        $this->entityManager()->clear();
        self::assertSame(['Murder party'], $this->titles($calendar, $from, $through));

        $published = $this->service(EventRepository::class)->get($proposal->id) ?? self::fail('Saved.');
        $this->expectException(NotEnabledTransitionException::class);
        $proposals->refuse($published);
    }

    /**
     * The month printed: each day's events, a date link on the items, a PDF of the paged-media template.
     */
    public function testTheCalendarPrintedAndTheDateLinks(): void
    {
        $event = $this->service(Calendar::class)->schedule($this->draft('Soirée', '2026-11-10 19:00', '2026-11-10 22:00', 'FREQ=WEEKLY;COUNT=2'));
        $event->linkDate(new DateTimeImmutable('2026-11-17 19:00', new DateTimeZone(self::ZONE)), 'https://billets.example/17');
        $this->service(EventRepository::class)->save($event);

        $printed = $this->service(PrintableCalendar::class);
        [$november] = $printed->months(2026, 11, 1, 'fr_CA');
        self::assertSame('Novembre 2026', $november->title);
        self::assertSame([10 => ['19 h 00 Soirée'], 17 => ['19 h 00 Soirée']], $november->events);
        self::assertSame(['Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi', 'Dimanche'], $printed->weekdays('fr_CA'));
        self::assertStringStartsWith('%PDF-', $printed->document(2026, 11, 2, 'en')->render());

        $views = $this->service(CalendarViews::class);
        $agenda = $views->agenda($views->period(CalendarView::List, new DateTimeImmutable('2026-11-10', new DateTimeZone(self::ZONE))));
        self::assertSame([null, 'https://billets.example/17'], array_map(static fn (Item $item): ?string => $item->url, array_merge(...array_map(static fn (AgendaGroup $group): array => $group->items, $agenda->groups))));
    }

    /**
     * @return list<string>
     */
    private function titles(Calendar $calendar, DateTimeImmutable $from, DateTimeImmutable $through): array
    {
        return array_map(static fn (Occurrence $occurrence): string => $occurrence->event->title(), $calendar->occurrences($from, $through));
    }

    /**
     * @param list<Occurrence> $occurrences
     *
     * @return list<string>
     */
    private function days(array $occurrences): array
    {
        return array_map(static fn (Occurrence $occurrence): string => $occurrence->event->title().' '.$occurrence->start->format('m-d'), $occurrences);
    }

    private function draft(string $title, string $start, string $end, ?string $rule = null): EventDraft
    {
        $draft = new EventDraft();
        $draft->title = $title;
        $draft->start = new DateTimeImmutable($start);
        $draft->end = new DateTimeImmutable($end);
        $draft->timezone = self::ZONE;
        $draft->rule = null === $rule ? null : RecurrenceRule::parse($rule);

        return $draft;
    }

    private function entityManager(): EntityManagerInterface
    {
        $entityManager = self::getContainer()->get('doctrine.orm.entity_manager');
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);

        return $entityManager;
    }

    private function twig(): Environment
    {
        $twig = self::getContainer()->get('twig');
        self::assertInstanceOf(Environment::class, $twig);

        return $twig;
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $id
     *
     * @return T
     */
    private function service(string $id): object
    {
        $service = self::getContainer()->get($id);
        self::assertInstanceOf($id, $service);

        return $service;
    }
}
