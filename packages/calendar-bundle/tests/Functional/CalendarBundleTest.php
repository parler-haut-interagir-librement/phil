<?php

declare(strict_types=1);

namespace Phil\CalendarBundle\Tests\Functional;

use DateTimeImmutable;
use DateTimeZone;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Locale;
use LogicException;
use Override;
use Phil\CalendarBundle\Application\Calendar;
use Phil\CalendarBundle\Application\EventDraft;
use Phil\CalendarBundle\Application\EventTagging;
use Phil\CalendarBundle\Application\VenueDraft;
use Phil\CalendarBundle\Application\Venues;
use Phil\CalendarBundle\Domain\Model\Event;
use Phil\CalendarBundle\Domain\Model\EventHost;
use Phil\CalendarBundle\Domain\Model\Venue;
use Phil\CalendarBundle\Domain\Repository\EventRepository;
use Phil\CalendarBundle\Domain\ValueObject\Occurrence;
use Phil\CalendarBundle\Infrastructure\Form\EventType;
use Phil\CalendarBundle\Infrastructure\Taxonomy\TaxonomyEventTagging;
use Phil\CalendarBundle\PhilCalendarBundle;
use Phil\CalendarBundle\Tests\Functional\App\TestKernel;
use Phil\Recurrence\RecurrenceRule;
use Phil\Resource\ResourceReference;
use Phil\TaxonomyBundle\Application\Taxonomy;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;
use Twig\Extension\CoreExtension;

#[CoversClass(PhilCalendarBundle::class)]
final class CalendarBundleTest extends KernelTestCase
{
    private string $locale;

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

        $this->locale = Locale::getDefault();
        Locale::setDefault('fr_CA');
        self::bootKernel();

        $entityManager = $this->service('doctrine.orm.entity_manager');
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);
        new SchemaTool($entityManager)->createSchema($entityManager->getMetadataFactory()->getAllMetadata());
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        Locale::setDefault($this->locale);
    }

    public function testEventsAreStoredAndFoundByPeriodHostAndTag(): void
    {
        $calendar = $this->calendar();
        $weekly = $calendar->schedule($this->draft('Soirée', '2026-11-10 19:00', '2026-11-10 22:00', 'FREQ=WEEKLY;COUNT=3', ['jeux']));
        $weekly->except(new DateTimeImmutable('2026-11-17 19:00', new DateTimeZone('America/Toronto')));

        $calendar->host($weekly, 'association', '42');
        $calendar->schedule($this->draft('Atelier', '2026-11-12 19:00', '2026-11-12 21:00', null, ['peinture']));
        $calendar->schedule($this->draft('Passé', '2026-01-12 19:00', '2026-01-12 21:00', 'FREQ=DAILY;UNTIL=20260115T000000Z'));
        $this->entityManager()->clear();

        $from = new DateTimeImmutable('2026-11-01', new DateTimeZone('America/Toronto'));
        $through = new DateTimeImmutable('2026-12-01', new DateTimeZone('America/Toronto'));

        self::assertSame(['Soirée 11-10 19:00 EST', 'Atelier 11-12 19:00 EST', 'Soirée 11-24 19:00 EST'], $this->titles($calendar->occurrences($from, $through)));
        self::assertSame(['Soirée 11-10 19:00 EST', 'Soirée 11-24 19:00 EST'], $this->titles($calendar->occurrences($from, $through, 'association', '42')));
        self::assertSame([], $this->titles($calendar->occurrences($from, $through, 'association', '43')));
        self::assertSame(['Atelier 11-12 19:00 EST'], $this->titles($calendar->occurrences($from, $through, tag: 'peinture')));
        self::assertSame(['Passé 01-14 19:00 EST'], $this->titles($calendar->occurrences(new DateTimeImmutable('2026-01-14 12:00 UTC'), new DateTimeImmutable('2026-01-20 UTC'))));

        $events = $this->service(EventRepository::class);
        self::assertInstanceOf(EventRepository::class, $events);
        $stored = $events->get($weekly->id);
        self::assertSame(['FREQ=WEEKLY;COUNT=3', ['jeux'], '2026-11-10T19:00:00-05:00', 1], [(string) $stored?->rule(), $stored?->tags(), $stored?->start()->format('c'), \count($events->hostsOf($stored ?? $weekly))]);

        $calendar->unhost($stored ?? $weekly, 'association', '42');
        $events->remove($stored ?? $weekly);
        $this->entityManager()->clear();
        self::assertNull($events->get($weekly->id));
        self::assertSame(['Atelier 11-12 19:00 EST'], $this->titles($calendar->occurrences($from, $through)));
    }

    public function testTheFeedsAnswer(): void
    {
        $calendar = $this->calendar();
        $calendar->schedule($this->draft('Soirée', '2026-11-10 19:00', '2026-11-10 22:00', 'FREQ=WEEKLY', ['jeux']));

        self::assertSame(404, $this->handle(Request::create('/calendar/events.json'))->getStatusCode(), 'the FullCalendar feed is gone');

        $ics = $this->handle(Request::create('/calendar/calendar.ics'));
        self::assertSame('text/calendar; charset=utf-8', $ics->headers->get('Content-Type'));
        self::assertStringContainsString("X-WR-CALNAME:Le Club\r\n", (string) $ics->getContent());
        self::assertStringContainsString("RRULE:FREQ=WEEKLY\r\n", (string) $ics->getContent());
    }

    public function testTheFormAndTheTemplatesSpeakTheLocale(): void
    {
        $forms = $this->service('test.form.factory');
        self::assertInstanceOf(FormFactoryInterface::class, $forms);
        $form = $forms->createNamed('event', EventType::class);
        $form->submit(['title' => '', 'start' => '2026-11-10T19:00', 'end' => '2026-11-10T22:00']);
        self::assertFalse($form->isValid());

        $twig = $this->service('twig');
        self::assertInstanceOf(Environment::class, $twig);
        $html = $twig->createTemplate('{{ form(form) }}')->render(['form' => $forms->createNamed('event', EventType::class)->createView()]);
        self::assertStringContainsString('<option value="America/Toronto" selected="selected">', $html);

        $this->calendar()->schedule($this->draft('Soirée', '2026-11-10 19:00', '2026-11-10 22:00'));
        $text = $twig->createTemplate('{% for o in calendar_occurrences(from, through) %}{{ o.event.title }} : {{ o|occurrence_when }}{% endfor %}')->render([
            'from' => new DateTimeImmutable('2026-11-01'),
            'through' => new DateTimeImmutable('2026-11-30'),
        ]);
        self::assertSame('Soirée : mardi 10 novembre 2026, 19 h 00 – 22 h 00', $text);
    }

    /**
     * @param list<string> $tags
     */
    private function draft(string $title, string $start, string $end, ?string $rule = null, array $tags = []): EventDraft
    {
        $draft = new EventDraft();
        $draft->title = $title;
        $draft->start = new DateTimeImmutable($start);
        $draft->end = new DateTimeImmutable($end);
        $draft->timezone = 'America/Toronto';
        $draft->rule = null === $rule ? null : RecurrenceRule::parse($rule);
        $draft->tags = $tags;

        return $draft;
    }

    private function handle(Request $request): Response
    {
        self::assertNotNull(self::$kernel);

        return self::$kernel->handle($request);
    }

    private function calendar(): Calendar
    {
        $calendar = $this->service(Calendar::class);
        self::assertInstanceOf(Calendar::class, $calendar);

        return $calendar;
    }

    private function entityManager(): EntityManagerInterface
    {
        $entityManager = $this->service('doctrine.orm.entity_manager');
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);

        return $entityManager;
    }

    /**
     * A venue kept, an event placed there with its poster, read back; the form offers the venues by their label.
     */
    public function testVenuesAndPosters(): void
    {
        $venues = $this->service(Venues::class);
        self::assertInstanceOf(Venues::class, $venues);
        $draft = new VenueDraft();
        $draft->name = 'Salle Le Royal';
        $draft->room = 'salle B';
        $draft->latitude = 46.8139;
        $draft->longitude = -71.2252;

        $venue = $venues->create($draft);
        $listed = $venues->ofListing('larp-site', '12', 'Terrain du Nord');
        $this->entityManager()->clear();
        self::assertSame([$venue->id, $listed->id], array_map(static fn (Venue $saved): string => $saved->id, $venues->all()), 'a venue is stored as soon as it is saved');
        [$venue, $listed] = $venues->all();

        $calendar = $this->service(Calendar::class);
        self::assertInstanceOf(Calendar::class, $calendar);
        $event = new EventDraft();
        $event->title = 'Salon';
        $event->start = new DateTimeImmutable('2026-09-19');
        $event->end = new DateTimeImmutable('2026-09-20');
        $event->allDay = true;
        $event->timezone = 'America/Toronto';
        $event->venue = $venue;
        $event->image = '/affiches/salon.jpg';

        $id = $calendar->schedule($event)->id;
        $event->venue = $listed;
        $elsewhere = $calendar->schedule($event)->id;

        $entityManager = $this->service('doctrine.orm.entity_manager');
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);
        $entityManager->clear();

        $events = $this->service(EventRepository::class);
        self::assertInstanceOf(EventRepository::class, $events);
        $stored = $events->get($id);
        self::assertSame(['Salle Le Royal — salle B', [46.8139, -71.2252], '/affiches/salon.jpg'], [$stored?->venue()?->label(), $stored?->venue()?->coordinates(), $stored?->image()]);
        self::assertSame(['Salle Le Royal — salle B', 'Terrain du Nord'], array_map(static fn (Venue $venue): string => $venue->label(), $venues->all()));
        self::assertSame($listed->id, $venues->ofListing('larp-site', '12', 'Ignored')->id);

        $factory = $this->service('test.form.factory');
        self::assertInstanceOf(FormFactoryInterface::class, $factory);
        $twig = $this->service('twig');
        self::assertInstanceOf(Environment::class, $twig);
        $html = $twig->createTemplate('{{ form(form) }}')->render(['form' => $factory->create(EventType::class)->createView()]);
        self::assertStringContainsString('>Salle Le Royal — salle B</option>', $html);
        self::assertStringContainsString('Lieu du calendrier', $html);

        $removed = $venues->all()[0];
        $venues->remove($removed);
        $entityManager->clear();
        self::assertNull($events->get($id)?->venue(), 'an event outlives its venue');
        self::assertSame('Terrain du Nord', $events->get($elsewhere)?->venue()?->label(), 'the events of another venue keep it');
        self::assertSame(['Terrain du Nord'], array_map(static fn (Venue $kept): string => $kept->label(), $venues->all()));
    }

    /**
     * The component in each view, in French: the toolbar's links work without JavaScript, the month's bars, the
     * week's times, the list's days in words, the posters.
     */
    public function testTheCalendarComponent(): void
    {
        $calendar = $this->service(Calendar::class);
        self::assertInstanceOf(Calendar::class, $calendar);
        $calendar->schedule($this->draft('Soirée', '2026-11-10 19:00', '2026-11-10 22:00', 'FREQ=WEEKLY;COUNT=3', ['jeux']));
        $festival = new EventDraft();
        $festival->title = 'Festival';
        $festival->start = new DateTimeImmutable('2026-11-13');
        $festival->end = new DateTimeImmutable('2026-11-15');
        $festival->allDay = true;
        $festival->timezone = 'America/Toronto';
        $festival->image = '/affiches/festival.jpg';

        $calendar->schedule($festival);

        $twig = $this->service('twig');
        self::assertInstanceOf(Environment::class, $twig);
        $render = static fn (string $view): string => $twig->createTemplate(\sprintf('<twig:PhilCalendar:Calendar view="%s" date="2026-11-12" />', $view))->render();

        $month = $render('month');
        self::assertStringContainsString('data-controller="phil--calendar-bundle--calendar live"', $month);
        self::assertStringContainsString('<h2 class="phil-calendar__title" aria-live="polite">novembre 2026</h2>', $month);
        self::assertStringContainsString('href="?view=month&amp;date=2026-10-12"', $month, 'a link to the month before, for a browser without JavaScript');
        self::assertStringContainsString('data-live-action-param="next"', $month);
        self::assertStringContainsString('aria-current="true"', $month);
        self::assertSame(3, substr_count($month, '<span class="phil-calendar__item-title">Soirée</span>'));
        self::assertStringContainsString('<span class="phil-calendar__item-title">Festival</span>', $month);
        self::assertStringContainsString('grid-column: 5 / span 3', $month, 'the festival, Friday to Sunday');
        self::assertStringContainsString('>19 h 00</time>', $month);

        $week = $render('week');
        self::assertStringContainsString('9 au 15 novembre', $week);
        self::assertStringContainsString('top: 79.1667%; height: 12.5%', $week, 'the evening, 19 h to 22 h');
        self::assertStringContainsString('Journée', $week);

        $list = $render('list');
        self::assertStringContainsString('mardi 10 novembre', $list);
        self::assertStringContainsString('13 au 15 novembre', $list);

        $posters = $render('posters');
        self::assertStringContainsString('src="/affiches/festival.jpg"', $posters);
        self::assertStringContainsString('<article data-slot="card" data-size="default" class="isCard">', $posters, 'the Card recipe of the kit');
        self::assertStringContainsString('<img class="isCardMedia phil-calendar__poster"', $posters);
        self::assertStringContainsString('data-slot="card-title" class="isCardTitle">Festival</h3>', $posters);

        self::assertStringContainsString('Rien pour cette période', $twig->createTemplate('<twig:PhilCalendar:Calendar view="list" date="2027-03-01" />')->render());
        self::assertStringContainsString('aria-current="true"', $twig->createTemplate('<twig:PhilCalendar:Calendar view="nope" date="nope" :views="[\'day\', \'list\']" />')->render(), 'an unknown view is the first offered');
        self::assertStringContainsString('<span class="phil-calendar__hidden">jeudi 12 novembre 2026</span>', $month, 'each day named in full');
        self::assertStringContainsString('data-slot="popover" data-state="closed"', $month, 'the Popover recipe of the kit');
        self::assertMatchesRegularExpression('/<button type="button" class="phil-calendar__item phil-calendar__item--event" data-slot="popover-trigger" data-popover-target="trigger" data-action="click-&gt;popover#toggle" aria-haspopup="dialog" data-state="closed" popovertarget="(phil-calendar-w\\d-[^"]+)">.*<div id="\\1" popover role="dialog" data-slot="popover-content"/s', $month);
        self::assertStringContainsString('data-slot="button-group" role="group" data-orientation="horizontal" class="isButtonGroup" aria-label="Période"', $month, 'the ButtonGroup recipe');
        self::assertStringContainsString('<a data-slot="button" data-variant="secondary" data-size="sm" class="isButton isSecondary isSmall" rel="prev" href="?view=month&amp;date=2026-10-12"', $month, 'the Button recipe, as a link');
        self::assertStringContainsString('data-variant="default" data-size="sm" class="isButton isPrimary isSmall" aria-current="true"', $month, 'the view shown');
        self::assertStringContainsString('data-slot="empty"', $twig->createTemplate('<twig:PhilCalendar:Calendar view="posters" date="2027-03-01" />')->render(), 'the Empty recipe');
        self::assertStringNotContainsString('role="grid', $month);

        // Twig behind the calendar's time zone: the days stay the calendar's.
        $core = $twig->getExtension(CoreExtension::class);
        $zone = $core->getTimezone();
        $core->setTimezone('Pacific/Pago_Pago');

        try {
            $east = $render('month');
            self::assertMatchesRegularExpression('/data-current\s[^>]*data-live-date-param="2026-11-12"/', $east);
            self::assertStringContainsString('<span aria-hidden="true">12</span>', $east);
            self::assertStringContainsString('href="?view=month&amp;date=2026-10-12"', $east);
        } finally {
            $core->setTimezone($zone);
        }
    }

    /**
     * The tags live in the taxonomy: one tag whatever its case or accents, renamed there and given back to the events.
     */
    public function testTheTagsLiveInTheTaxonomy(): void
    {
        $calendar = $this->calendar();
        $taxonomy = $this->service(Taxonomy::class);
        self::assertInstanceOf(Taxonomy::class, $taxonomy);
        self::assertInstanceOf(TaxonomyEventTagging::class, $this->service(EventTagging::class));

        $first = $calendar->schedule($this->draft('Soirée', '2026-11-10 19:00', '2026-11-10 22:00', null, ['!!!', 'Jeux de rôle']));
        $second = $calendar->schedule($this->draft('Atelier', '2026-11-12 19:00', '2026-11-12 21:00', null, ['jeux de role', 'peinture']));

        self::assertSame(['Jeux de rôle'], $first->tags());
        self::assertSame(['Jeux de rôle', 'peinture'], $second->tags());
        $tag = $taxonomy->tag('jeux de rôle');
        self::assertSame([TaxonomyEventTagging::RESOURCE.':'.$first->id, TaxonomyEventTagging::RESOURCE.':'.$second->id], array_map(static fn (ResourceReference $resource): string => $resource->toString(), $taxonomy->taggedWith($tag, TaxonomyEventTagging::RESOURCE)));

        $taxonomy->renameTag($tag, 'JdR');
        $application = new Application(self::$kernel ?? throw new LogicException('The kernel is booted.'));
        $command = new CommandTester($application->find('phil:calendar:refresh-tags'));
        $command->execute([]);
        self::assertSame('2 events had their tags renamed.', trim($command->getDisplay()));
        $this->entityManager()->clear();

        $events = $this->service(EventRepository::class);
        self::assertInstanceOf(EventRepository::class, $events);
        $reread = $events->get($second->id);
        self::assertNotNull($reread);
        self::assertSame(['JdR', 'peinture'], $reread->tags());

        $calendar->remove($reread);
        self::assertNull($events->get($second->id));
        self::assertSame([TaxonomyEventTagging::RESOURCE.':'.$first->id], array_map(static fn (ResourceReference $resource): string => $resource->toString(), $taxonomy->taggedWith($taxonomy->tag('JdR'))));
    }

    /**
     * The repository on Doctrine: every event by its start, the hosts of one or of several events by kind then
     * reference, a host saved and removed, the events of one host only when both its kind and reference are given.
     */
    public function testTheDoctrineRepository(): void
    {
        $calendar = $this->calendar();
        $events = $this->service(EventRepository::class);
        self::assertInstanceOf(EventRepository::class, $events);
        $late = $calendar->schedule($this->draft('Tard', '2026-11-12 19:00', '2026-11-12 20:00'));
        $early = $calendar->schedule($this->draft('Tôt', '2026-11-10 19:00', '2026-11-10 20:00'));
        $alone = $calendar->schedule($this->draft('Seul', '2026-11-11 19:00', '2026-11-11 20:00'));
        $calendar->host($late, 'shop', '1');
        $calendar->host($late, 'association', '9');
        $calendar->host($late, 'association', '10');
        $calendar->host($early, 'shop', '2');
        $this->entityManager()->clear();

        self::assertSame(['Tôt', 'Seul', 'Tard'], array_map(static fn (Event $event): string => $event->title(), $events->all()));
        $hosts = $this->hostNames(...);
        $reread = $events->get($late->id) ?? throw new LogicException('Saved.');
        self::assertSame(['association:10', 'association:9', 'shop:1'], $hosts($events->hostsOf($reread)));

        $each = $events->hostsOfEach([$reread, $events->get($early->id) ?? $early, $events->get($alone->id) ?? $alone]);
        self::assertSame([$late->id, $early->id, $alone->id], array_keys($each));
        self::assertSame([['association:10', 'association:9', 'shop:1'], ['shop:2'], []], array_values(array_map($hosts, $each)));
        self::assertSame([], $events->hostsOfEach([]));
        self::assertSame([$early->id], array_keys($events->hostsOfEach([$events->get($early->id) ?? $early])), 'the hosts of the events asked for alone');

        $calendar->unhost($reread, 'shop', '1');
        $this->entityManager()->clear();
        $unhosted = $events->get($late->id);
        self::assertInstanceOf(Event::class, $unhosted);
        self::assertSame(['association:10', 'association:9'], $hosts($events->hostsOf($unhosted)));

        $from = new DateTimeImmutable('2026-11-01 UTC');
        $through = new DateTimeImmutable('2026-12-01 UTC');
        self::assertSame(['Tard'], array_map(static fn (Event $event): string => $event->title(), $events->overlapping($from, $through, 'association', '9')));
        self::assertCount(3, $events->overlapping($from, $through, 'association'), 'a kind without a reference filters nothing');
    }

    /**
     * @param list<EventHost> $hosts
     *
     * @return list<string>
     */
    private function hostNames(array $hosts): array
    {
        return array_map(static fn (EventHost $host): string => $host->kind.':'.$host->reference, $hosts);
    }

    private function service(string $id): object
    {
        $service = self::getContainer()->get($id);
        self::assertIsObject($service);

        return $service;
    }

    /**
     * @param list<Occurrence> $occurrences
     *
     * @return list<string>
     */
    private function titles(array $occurrences): array
    {
        return array_map(static fn (Occurrence $occurrence): string => $occurrence->event->title().' '.$occurrence->start->format('m-d H:i T'), $occurrences);
    }
}
